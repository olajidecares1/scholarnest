<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Concerns\AcceptsCbtDocumentUploads;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\AuditLog;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use App\Services\CbtDocumentImportService;
use App\Services\CbtExtractionAvailability;
use App\Services\CbtExtractionRunner;
use App\Services\QueueWorkerHealth;
use App\Services\Uploads\UploadStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function store(Request $request, CbtExtractionRunner $runner): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'file' => $this->documentRules(),
            'cbt_exam_body_id' => ['nullable', 'integer', 'exists:cbt_exam_bodies,id'],
            'cbt_subject_id' => ['nullable', 'integer', 'exists:cbt_subjects,id'],
            // Only for a paper that never prints its own year. A compilation's
            // year headings still decide where each of its questions goes.
            'year' => ['nullable', 'integer', 'min:1960', 'max:'.(now()->year + 1)],
            'upload_again' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', (string) $file->getRealPath()) ?: null;

        // The same document uploaded again would import every question again.
        // Caught here, before anything is stored, unless it is deliberate.
        $previous = $hash ? CbtDocumentUpload::where('file_hash', $hash)
            ->where('status', '!=', CbtDocumentUploadStatus::Failed)
            ->latest()
            ->first() : null;

        if ($previous && ! $request->boolean('upload_again')) {
            throw ValidationException::withMessages([
                'file' => sprintf(
                    'This exact document was already uploaded on %s as "%s". Open that upload to review or publish it. '
                        .'To import it again anyway, tick "Upload this document again".',
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
        $path = app(UploadStorage::class)->storeFile($file, 'local', 'cbt-uploads/documents', 'file');

        $upload = CbtDocumentUpload::create([
            'uploaded_by' => $request->user()->id,
            'cbt_exam_body_id' => $validated['cbt_exam_body_id'] ?? null,
            'cbt_subject_id' => $validated['cbt_subject_id'] ?? null,
            'year' => $validated['year'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_hash' => $hash,
            'status' => CbtDocumentUploadStatus::Pending,
        ]);

        $runner->start(new ProcessCbtDocumentUpload($upload));

        AuditLog::record('cbt.document.uploaded', "Uploaded \"{$upload->original_filename}\" for CBT extraction.", $upload);

        if ($request->wantsJson()) {
            return response()->json([
                'status_url' => route('super-admin.cbt.uploads.status', $upload),
                'redirect_url' => route('super-admin.cbt.uploads.show', $upload),
            ], 201);
        }

        return redirect()->route('super-admin.cbt.uploads.show', $upload)
            ->with('status', 'Document uploaded. Reading the questions now.');
    }

    public function show(CbtDocumentUpload $upload, CbtExtractionRunner $runner): View
    {
        $runner->catchUp($upload, new ProcessCbtDocumentUpload($upload));

        $upload->load([
            'examBody',
            'subject',
            'questions' => fn ($query) => $query->with(['exam', 'options'])->orderBy('cbt_exam_id')->orderBy('sort_order'),
        ]);

        return view('super-admin.cbt.uploads.show', [
            'stalled' => $runner->isStalled($upload),
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

        return back()->with('status', "Imported {$result['extracted']} question(s), {$result['needs_review']} need review. Review them below, then publish.");
    }

    /**
     * Make an upload's reviewed questions available to students.
     */
    public function publish(CbtDocumentUpload $upload, CbtDocumentImportService $importer): RedirectResponse
    {
        abort_unless($upload->status === CbtDocumentUploadStatus::Completed, 409, 'Only a finished extraction can be published.');

        $result = $importer->publish($upload);

        AuditLog::record(
            'cbt.document.published',
            "Published {$result['published']} question(s) from \"{$upload->original_filename}\".",
            $upload
        );

        return back()->with('status', $result['held_back'] > 0
            ? "Published {$result['published']} question(s). {$result['held_back']} still need review and stay hidden from students until they are corrected in the question bank."
            : "Published {$result['published']} question(s). Students can now practise them.");
    }

    /**
     * Hide an upload's questions from students again.
     */
    public function unpublish(CbtDocumentUpload $upload): RedirectResponse
    {
        $count = CbtQuestion::where('cbt_document_upload_id', $upload->id)->update(['is_published' => false]);
        $upload->update(['published_at' => null]);

        AuditLog::record('cbt.document.unpublished', "Unpublished {$count} question(s) from \"{$upload->original_filename}\".", $upload);

        return back()->with('status', "{$count} question(s) are hidden from students again.");
    }

    public function destroy(CbtDocumentUpload $upload): RedirectResponse
    {
        Storage::disk($upload->disk)->delete($upload->path);

        // Questions students have never seen go with the upload. Published
        // ones stay in the question bank, and so do the pictures they use.
        CbtQuestion::where('cbt_document_upload_id', $upload->id)->where('is_published', false)->delete();
        $inUse = CbtQuestion::whereIn('image_path', $upload->extracted_images ?? [])->pluck('image_path')->all();

        foreach (array_diff($upload->extracted_images ?? [], $inUse) as $imagePath) {
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
    public function status(CbtDocumentUpload $upload, CbtExtractionRunner $runner, QueueWorkerHealth $queue): JsonResponse
    {
        $runner->catchUp($upload, new ProcessCbtDocumentUpload($upload));

        $stalled = $runner->isStalled($upload);

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
    public function retry(Request $request, CbtDocumentUpload $upload, CbtExtractionRunner $runner): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1960', 'max:'.(now()->year + 1)],
        ]);

        // Reading again replaces this upload's questions. Once students can see
        // them, that would take away questions they may already have answered.
        if (CbtQuestion::where('cbt_document_upload_id', $upload->id)->where('is_published', true)->exists()) {
            return back()->withErrors([
                'upload' => 'Questions from this upload are already published, so it cannot be read again. Unpublish it first, or correct individual questions in the question bank.',
            ]);
        }

        $upload->update([
            'status' => CbtDocumentUploadStatus::Pending,
            'error_message' => null,
            // Only when one is given: a retry without a year keeps whatever
            // year the upload was made with.
            ...($request->filled('year') ? ['year' => (int) $validated['year']] : []),
        ]);

        $runner->start(new ProcessCbtDocumentUpload($upload));

        return back()->with('status', 'Reading the document again.');
    }
}
