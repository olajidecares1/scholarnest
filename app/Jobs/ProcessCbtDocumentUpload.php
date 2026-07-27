<?php

namespace App\Jobs;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Services\CbtDocumentExtractionService;
use App\Services\CbtDocumentImportService;
use App\Services\CbtDocxTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessCbtDocumentUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public CbtDocumentUpload $upload) {}

    public function handle(
        CbtDocumentExtractionService $extractor,
        CbtDocxTextExtractor $docxExtractor,
        CbtDocumentImportService $importer,
    ): void {
        $this->upload->update(['status' => CbtDocumentUploadStatus::Processing]);

        try {
            $knownExamBody = $this->upload->examBody?->code;
            $knownSubject = $this->upload->subject?->name;

            if ($this->upload->mime_type === 'application/pdf') {
                $data = $extractor->extractFromPdf($this->upload->absolutePath(), $knownExamBody, $knownSubject);
            } else {
                $docx = $docxExtractor->extract($this->upload->absolutePath());
                $data = $extractor->extractFromText($docx['text'], $knownExamBody, $knownSubject);
                $this->upload->update(['extracted_images' => $docx['images']]);
            }

            $this->upload->update([
                'ai_response' => $data,
                'detected_years' => collect($data['years'] ?? [])->pluck('year')->all(),
            ]);

            $examBody = $this->upload->examBody ?? $this->matchExamBody($data['exam_body'] ?? '');
            $subject = $this->upload->subject ?? $this->matchSubject($data['subject'] ?? '');

            if (! $examBody || ! $subject) {
                $this->upload->update(['status' => CbtDocumentUploadStatus::NeedsMapping]);

                return;
            }

            $importer->import($this->upload, $examBody, $subject);
        } catch (Throwable $e) {
            $this->upload->update([
                'status' => CbtDocumentUploadStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function matchExamBody(string $name): ?CbtExamBody
    {
        if ($name === '') {
            return null;
        }

        return CbtExamBody::whereRaw('LOWER(code) = ?', [strtolower($name)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
    }

    private function matchSubject(string $name): ?CbtSubject
    {
        if ($name === '') {
            return null;
        }

        return CbtSubject::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
