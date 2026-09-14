<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Concerns\AcceptsCbtDocumentUploads;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\AuditLog;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Services\CbtDocumentImportService;
use App\Services\CbtExtractionAvailability;
use App\Services\QueueWorkerHealth;
use App\Services\Uploads\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CbtDocumentUploadController extends Controller
{
    use AcceptsCbtDocumentUploads;

    public function index(Request $request, CbtExtractionAvailability $availability): View
    {
        // Arriving from a particular exam body's page pre-selects it, so
        // somebody who was managing JAMB and clicked Upload does not have to
        // say "JAMB" again on the next screen.
        $examBodies = CbtExamBody::orderBy('name')->get();

        return view('super-admin.cbt.uploads.index', [
            // The AkademicNest Team can start a worker, so they are the audience
            // that gets told which command does it.
            'extractionWarning' => $availability->warning(canOperateTheServer: true),
            'uploads' => CbtDocumentUpload::with(['uploadedBy', 'examBody', 'subject'])
                ->latest()
                ->paginate(15),
            'examBodies' => $examBodies,
            'subjects' => CbtSubject::orderBy('name')->get(),
            'selectedExamBodyId' => $examBodies
                ->firstWhere('uuid', $request->string('exam_body')->toString())?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'file' => $this->documentRules(),
            'cbt_exam_body_id' => ['nullable', 'integer', 'exists:cbt_exam_bodies,id'],
            'cbt_subject_id' => ['nullable', 'integer', 'exists:cbt_subjects,id'],
        ]);

        $file = $request->file('file');
        // Used to record which format this is, not to name the file: the
        // stored name comes from the content (StoredUpload). The validation
        // rule above has already restricted this to the two we read.
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = app(UploadStorage::class)->storeFile($file, 'local', 'cbt-uploads/documents', 'file');

        $upload = CbtDocumentUpload::create([
            'uploaded_by' => $request->user()->id,
            'cbt_exam_body_id' => $validated['cbt_exam_body_id'] ?? null,
            'cbt_subject_id' => $validated['cbt_subject_id'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => CbtDocumentUploadStatus::Pending,
        ]);

        ProcessCbtDocumentUpload::dispatch($upload);

        AuditLog::record('cbt.document.uploaded', "Uploaded \"{$upload->original_filename}\" for CBT extraction.", $upload);

        if ($request->wantsJson()) {
            return response()->json([
                'status_url' => route('super-admin.cbt.uploads.status', $upload),
                'redirect_url' => route('super-admin.cbt.uploads.show', $upload),
            ], 201);
        }

        return redirect()->route('super-admin.cbt.uploads.show', $upload)
            ->with('status', 'Document uploaded. Extraction is running in the background.');
    }

    public function show(CbtDocumentUpload $upload, QueueWorkerHealth $queue): View
    {
        $upload->load([
            'examBody',
            'subject',
            'questions' => fn ($query) => $query->with(['exam', 'options'])->orderBy('cbt_exam_id')->orderBy('sort_order'),
        ]);

        return view('super-admin.cbt.uploads.show', [
            'stalled' => $upload->status === CbtDocumentUploadStatus::Pending && ! $queue->isRunning(),
            'upload' => $upload,
            'examBodies' => CbtExamBody::orderBy('name')->get(),
            'subjects' => CbtSubject::orderBy('name')->get(),
        ]);
    }

    public function confirmMapping(Request $request, CbtDocumentUpload $upload, CbtDocumentImportService $importer): RedirectResponse
    {
        $validated = $request->validate([
            'cbt_exam_body_id' => ['required', 'integer', 'exists:cbt_exam_bodies,id'],
            'cbt_subject_id' => ['required', 'integer', 'exists:cbt_subjects,id'],
        ]);

        $examBody = CbtExamBody::findOrFail($validated['cbt_exam_body_id']);
        $subject = CbtSubject::findOrFail($validated['cbt_subject_id']);

        $result = $importer->import($upload, $examBody, $subject);

        AuditLog::record(
            'cbt.document.imported',
            "Imported {$result['extracted']} question(s) from \"{$upload->original_filename}\".",
            $upload
        );

        return back()->with('status', "Imported {$result['extracted']} question(s), {$result['needs_review']} need review.");
    }

    public function destroy(CbtDocumentUpload $upload): RedirectResponse
    {
        Storage::disk($upload->disk)->delete($upload->path);

        foreach ($upload->extracted_images ?? [] as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        $name = $upload->original_filename;
        $upload->delete();

        AuditLog::record('cbt.document.deleted', "Deleted uploaded document \"{$name}\".");

        return redirect()->route('super-admin.cbt.uploads.index')->with('status', "\"{$name}\" deleted successfully.");
    }

    /**
     * The live state of one upload, for the progress interface to poll.
     *
     * Deliberately thin: a status, a message, and whether to keep asking. The
     * page decides how to draw it.
     */
    public function status(CbtDocumentUpload $upload, QueueWorkerHealth $queue): JsonResponse
    {
        $stalled = $upload->status === CbtDocumentUploadStatus::Pending && ! $queue->isRunning();

        return response()->json([
            'status' => $upload->status->value,
            'label' => $upload->status->label(),
            'message' => $stalled
                ? $queue->stalledMessage(canOperateTheServer: true)
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
     * The file is already stored, so this re-runs the extraction rather than
     * asking for the document again, which matters most in the case this was
     * written for, where nothing was wrong with the upload and the queue
     * simply was not running.
     */
    public function retry(CbtDocumentUpload $upload, QueueWorkerHealth $queue): RedirectResponse
    {
        $upload->update([
            'status' => CbtDocumentUploadStatus::Pending,
            'error_message' => null,
        ]);

        ProcessCbtDocumentUpload::dispatch($upload);
        $queue->forget();

        return back()->with('status', 'Extraction has been queued again.');
    }
}
