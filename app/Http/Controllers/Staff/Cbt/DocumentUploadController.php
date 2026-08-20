<?php

namespace App\Http\Controllers\Staff\Cbt;

use App\Enums\CbtDocumentUploadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestDocumentUpload;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentUploadController extends Controller
{
    private const ALLOWED_MIMES = ['pdf', 'doc', 'docx'];

    public function store(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_MIMES), 'max:20480'],
        ]);

        $file = $validated['file'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs('cbt-test-uploads/documents', (string) Str::uuid().'.'.$extension, 'local');

        $upload = $test->documentUploads()->create([
            'staff_id' => $request->user('staff')->id,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => CbtDocumentUploadStatus::Pending,
        ]);

        ProcessCbtTestDocumentUpload::dispatch($upload);

        return redirect()->route('staff.cbt.tests.show', [$school, $test])->with('status', 'Document uploaded. Extraction is running in the background.');
    }

    public function show(Request $request, School $school, CbtTest $test, CbtTestDocumentUpload $upload): View
    {
        $this->authorizeTest($request, $test);
        abort_unless($upload->cbt_test_id === $test->id, 403);

        $upload->load('questions.options');

        return view('staff.cbt.upload', [
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

    private function authorizeTest(Request $request, CbtTest $test): void
    {
        abort_unless($test->staff_id === $request->user('staff')->id, 403);
    }
}
