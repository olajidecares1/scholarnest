<?php

namespace App\Http\Controllers\Staff\Cbt;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Concerns\AcceptsCbtDocumentUploads;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestDocumentUpload;
use App\Models\School;
use App\Services\CbtExtractionRunner;
use App\Services\QueueWorkerHealth;
use App\Services\Uploads\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentUploadController extends Controller
{
    use AcceptsCbtDocumentUploads;

    public function store(Request $request, School $school, CbtTest $test, CbtExtractionRunner $runner): RedirectResponse|JsonResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $request->validate([
            'file' => $this->documentRules(),
            'upload_again' => ['nullable', 'boolean'],
        ]);

        $file = $validated['file'];
        $hash = hash_file('sha256', (string) $file->getRealPath()) ?: null;

        // The same paper uploaded to the same test twice would read every
        // question again. Caught here, before anything is stored, unless the
        // teacher says they mean it.
        $previous = $hash ? $test->documentUploads()
            ->where('file_hash', $hash)
            ->where('status', '!=', CbtDocumentUploadStatus::Failed)
            ->latest()
            ->first() : null;

        if ($previous && ! $request->boolean('upload_again')) {
            throw ValidationException::withMessages([
                'file' => sprintf(
                    'You already uploaded this exact document to this test on %s as "%s". Open that upload to see its questions. '
                        .'To read it again anyway, tick "Upload this document again".',
                    $previous->created_at->format('j M Y, g:ia'),
                    $previous->original_filename,
                ),

                // Named so the upload form knows to offer "Upload this
                // document again" rather than just showing the message.
                'upload_again' => 'This document has been uploaded before.',
            ]);
        }

        // Used to record which format this is, not to name the file: the
        // stored name comes from the content (StoredUpload). The validation
        // rule above has already restricted this to the two we read.
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = app(UploadStorage::class)->storeFile($file, 'local', 'cbt-test-uploads/documents', 'file');

        $upload = $test->documentUploads()->create([
            'staff_id' => $request->user('staff')->id,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_hash' => $hash,
            'status' => CbtDocumentUploadStatus::Pending,
        ]);

        $runner->start(new ProcessCbtTestDocumentUpload($upload));

        if ($request->wantsJson()) {
            return response()->json([
                'status_url' => route('staff.cbt.tests.uploads.status', [$school, $test, $upload]),
                'redirect_url' => route('staff.cbt.tests.uploads.show', [$school, $test, $upload]),
            ], 201);
        }

        return redirect()->route('staff.cbt.tests.uploads.show', [$school, $test, $upload])
            ->with('status', 'Document uploaded. Reading the questions now.');
    }

    public function show(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, CbtExtractionRunner $runner): View
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $runner->catchUp($upload, new ProcessCbtTestDocumentUpload($upload));

        $upload->load([
            'questions' => fn ($query) => $query->with(['options', 'test'])->orderBy('cbt_test_id')->orderBy('sort_order'),
        ]);

        return view('staff.cbt.upload', [
            'stalled' => $runner->isStalled($upload),
            'school' => $school,
            'test' => $test,
            'upload' => $upload,

            // Every test this document filled, earliest year first, when it
            // held more than one year.
            'yearTests' => CbtTest::where('source_upload_id', $upload->id)
                ->orWhere(fn ($query) => $query->where('id', $test->id)->whereNotNull('source_year'))
                ->withCount('questions')
                ->orderBy('source_year')
                ->get(),
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
    public function status(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, CbtExtractionRunner $runner, QueueWorkerHealth $queue): JsonResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $runner->catchUp($upload, new ProcessCbtTestDocumentUpload($upload));

        $stalled = $runner->isStalled($upload);

        return response()->json([
            'status' => $upload->status->value,
            'label' => $upload->status->label(),
            'message' => $stalled
                ? $queue->stalledMessage(canOperateTheServer: false)
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
    public function retry(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload, CbtExtractionRunner $runner): RedirectResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        // Reading again replaces this document's questions in every test it
        // filled. A student's answers are tied to those questions, so once
        // anyone has answered or handed in, the paper stays as it is.
        $tests = $upload->questions()->distinct()->pluck('cbt_test_id')
            ->merge(CbtTest::where('source_upload_id', $upload->id)->pluck('id'))
            ->push($test->id)
            ->unique();

        $answered = CbtTestAttempt::whereIn('cbt_test_id', $tests)
            ->where(fn ($query) => $query->whereNotNull('submitted_at')->orWhereHas('answers'))
            ->exists();

        if ($answered) {
            return back()->withErrors([
                'upload' => 'Students have already answered questions from this document, so it cannot be read again: '
                    .'that would remove their answers. Duplicate the test and upload the document to the copy instead.',
            ]);
        }

        $upload->update([
            'status' => CbtDocumentUploadStatus::Pending,
            'error_message' => null,
        ]);

        $runner->start(new ProcessCbtTestDocumentUpload($upload));

        return back()->with('status', 'Reading the document again.');
    }

    private function authorizeTest(Request $request, CbtTest $test): void
    {
        abort_unless($test->staff_id === $request->user('staff')->id, 403);
    }
}
