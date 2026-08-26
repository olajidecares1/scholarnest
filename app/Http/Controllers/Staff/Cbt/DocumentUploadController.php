<?php

namespace App\Http\Controllers\Staff\Cbt;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Concerns\AcceptsCbtDocumentUploads;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\School;
use App\Services\QueueWorkerHealth;
use App\Support\StoredUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentUploadController extends Controller
{
    use AcceptsCbtDocumentUploads;

    public function store(Request $request, School $school, CbtTest $test): RedirectResponse|JsonResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $request->validate([
            'file' => $this->documentRules(),
        ]);

        $file = $validated['file'];
        // Used to record which format this is, not to name the file: the
        // stored name comes from the content (StoredUpload). The validation
        // rule above has already restricted this to the two we read.
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs('cbt-test-uploads/documents', StoredUpload::name($file), 'local');

        $upload = $test->documentUploads()->create([
            'staff_id' => $request->user('staff')->id,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => CbtDocumentUploadStatus::Pending,
        ]);

        ProcessCbtTestDocumentUpload::dispatch($upload);

        if ($request->wantsJson()) {
            return response()->json([
                'status_url' => route('staff.cbt.tests.uploads.status', [$school, $test, $upload]),
                'redirect_url' => route('staff.cbt.tests.uploads.show', [$school, $test, $upload]),
            ], 201);
        }

        return redirect()->route('staff.cbt.tests.uploads.show', [$school, $test, $upload])
            ->with('status', 'Document uploaded. Extraction is running in the background.');
    }

    public function show(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, QueueWorkerHealth $queue): View
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $upload->load('questions.options');

        return view('staff.cbt.upload', [
            'stalled' => $upload->status === CbtDocumentUploadStatus::Pending && ! $queue->isRunning(),
            'school' => $school,
            'test' => $test,
            'upload' => $upload,
        ]);
    }

    public function destroy(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload): RedirectResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        Storage::disk($upload->disk)->delete($upload->path);

        foreach ($upload->extracted_images ?? [] as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        $upload->delete();

        return redirect()->route('staff.cbt.tests.show', [$school, $test])->with('status', 'Upload removed.');
    }

    /**
     * The live state of one upload, for the progress interface to poll.
     */
    public function status(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, QueueWorkerHealth $queue): JsonResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $stalled = $upload->status === CbtDocumentUploadStatus::Pending && ! $queue->isRunning();

        return response()->json([
            'status' => $upload->status->value,
            'label' => $upload->status->label(),
            'message' => $stalled
                ? 'Waiting for the extraction service. Nothing is processing jobs at the moment.'
                : $upload->status->progressMessage(),
            'percent' => $upload->status->progressPercent(),
            'in_progress' => $upload->status->isInProgress(),
            'stalled' => $stalled,
            'question_count' => $upload->questions()->count(),
            'error' => $upload->error_message,
        ]);
    }

    /**
     * Put a failed or stalled upload back on the queue.
     *
     * The document is already stored, so this re-runs the extraction rather
     * than asking the teacher to find and upload the file a second time.
     */
    public function retry(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, QueueWorkerHealth $queue): RedirectResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $upload->update([
            'status' => CbtDocumentUploadStatus::Pending,
            'error_message' => null,
        ]);

        ProcessCbtTestDocumentUpload::dispatch($upload);
        $queue->forget();

        return back()->with('status', 'Extraction has been queued again.');
    }

    private function authorizeTest(Request $request, CbtTest $test): void
    {
        abort_unless($test->staff_id === $request->user('staff')->id, 403);
    }
}
