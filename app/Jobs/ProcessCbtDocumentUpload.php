<?php

namespace App\Jobs;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Services\CbtDocumentImportService;
use App\Services\DocumentExtraction\ExtractionResult;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Services\Uploads\UploadStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turn an uploaded past-paper document into catalogue questions.
 *
 * Extraction is local, PHPWord for .docx, smalot/pdfparser for .pdf, and a PHP
 * parser for the questions. Nothing reaches the network.
 *
 * The one thing a local parser cannot do as well as a person is decide which
 * examination body and subject a document belongs to. It does not guess: when
 * the uploader did not say and the document does not make it obvious, the
 * upload stops at NeedsMapping and asks, which is the state that already
 * existed for exactly this case.
 */
class ProcessCbtDocumentUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /**
     * An upload deleted before its job ran is not a failure, see the same
     * note on ProcessCbtTestDocumentUpload. The job looks the record up again
     * when it runs, so one that has since been removed would otherwise raise a
     * failed job over somebody changing their mind.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(public CbtDocumentUpload $upload) {}

    public function handle(QuestionExtractionProvider $extractor, CbtDocumentImportService $importer): void
    {
        if (! $this->claim()) {
            return;
        }

        try {
            $result = $extractor->extract($this->upload->absolutePath(), $this->upload->mime_type);

            $payload = $this->groupByYear($result);

            $this->upload->update([
                'extracted_images' => $result->images,
                'ai_response' => $payload,
                'detected_years' => collect($payload['years'])->pluck('year')->all(),
            ]);

            if ($result->isEmpty()) {
                $this->fail($result->failureReason());

                return;
            }

            $examBody = $this->upload->examBody ?? $this->matchExamBody($payload['exam_body'] ?? '');
            $subject = $this->upload->subject ?? $this->matchSubject($payload['subject'] ?? '');

            if (! $examBody || ! $subject) {
                $this->upload->update(['status' => CbtDocumentUploadStatus::NeedsMapping]);

                return;
            }

            $this->upload->update(['status' => CbtDocumentUploadStatus::Importing]);

            $importer->import($this->upload, $examBody, $subject);
        } catch (Throwable $e) {
            Log::error('CBT catalogue document extraction failed', [
                'upload_id' => $this->upload->id,
                'exception' => $e,
            ]);

            $this->fail(
                'Document extraction failed. Please check the document format and try again. '
                .'The file is stored safely, so it does not need to be uploaded again.'
            );
        } finally {
            // A worker does not end between jobs, so the temporary local copy
            // of a document held in object storage is removed here.
            UploadStorage::releaseLocalCopies();
        }
    }

    /**
     * Split the questions into the per-year papers the catalogue stores.
     *
     * A compilation prints a year heading above each paper, and the parser
     * records which one each question fell under. Where a document names no
     * year at all, an ordinary single paper, everything goes into one group
     * under the upload's own year, and the reviewer can correct it.
     *
     * @return array{exam_body: string, subject: string, years: list<array{year: int, questions: list<array<string, mixed>>, instructions: ?string}>}
     */
    private function groupByYear(ExtractionResult $result): array
    {
        $fallbackYear = (int) ($this->upload->created_at?->year ?? now()->year);

        $grouped = collect($result->questions)
            ->groupBy(fn (array $question) => (int) ($question['year'] ?? 0) ?: $fallbackYear)
            ->map(fn ($questions, $year) => [
                'year' => (int) $year,
                'instructions' => $result->instructions,

                // values() so the group is a list, and the order within a
                // paper is the order the document printed.
                'questions' => $questions->values()->all(),
            ])
            ->sortKeys()
            ->values()
            ->all();

        return [
            'exam_body' => (string) ($this->upload->examBody?->code ?? ''),
            'subject' => (string) ($this->upload->subject?->name ?? ''),
            'years' => $grouped,
        ];
    }

    /**
     * Take the upload, or decline if something else already has it.
     */
    private function claim(): bool
    {
        return DB::transaction(function (): bool {
            $fresh = CbtDocumentUpload::where('id', $this->upload->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status === CbtDocumentUploadStatus::Processing) {
                return false;
            }

            $fresh->update(['status' => CbtDocumentUploadStatus::Processing]);
            $this->upload = $fresh;

            return true;
        });
    }

    private function fail(string $message): void
    {
        $this->upload->update([
            'status' => CbtDocumentUploadStatus::Failed,
            'error_message' => $message,
        ]);
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
