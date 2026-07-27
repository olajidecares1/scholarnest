<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\AuditLog;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Services\CbtDocumentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CbtDocumentUploadController extends Controller
{
    private const ALLOWED_MIMES = ['pdf', 'doc', 'docx'];

    public function index(): View
    {
        return view('super-admin.cbt.uploads.index', [
            'uploads' => CbtDocumentUpload::with(['uploadedBy', 'examBody', 'subject'])
                ->latest()
                ->paginate(15),
            'examBodies' => CbtExamBody::orderBy('name')->get(),
            'subjects' => CbtSubject::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_MIMES), 'max:20480'],
            'cbt_exam_body_id' => ['nullable', 'integer', 'exists:cbt_exam_bodies,id'],
            'cbt_subject_id' => ['nullable', 'integer', 'exists:cbt_subjects,id'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs('cbt-uploads/documents', (string) Str::uuid().'.'.$extension, 'local');

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

        return redirect()->route('super-admin.cbt.uploads.show', $upload)->with('status', 'Document uploaded. Extraction is running in the background.');
    }

    public function show(CbtDocumentUpload $upload): View
    {
        $upload->load([
            'examBody',
            'subject',
            'questions' => fn ($query) => $query->with(['exam', 'options'])->orderBy('cbt_exam_id')->orderBy('sort_order'),
        ]);

        return view('super-admin.cbt.uploads.show', [
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
}
