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

    /**
     * A JAMB compilation runs to sixty-odd pages of two-column text, every
     * page is read position by position to come out in printed order, and a
     * PDF is read twice so the better reading can be kept. Measured at around
     * thirteen minutes for the largest paper seen, so the limit is set well
     * clear of it: a slow read is not a failure.
     */
    public int $timeout = 1800;

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

    /**
     * Set when some questions had to be filed under the year of upload.
     */
    private ?string $undatedWarning = null;

    public function __construct(public CbtDocumentUpload $upload) {}

    public function handle(QuestionExtractionProvider $extractor, CbtDocumentImportService $importer): void
    {
        if (! $this->claim()) {
            return;
        }

        // Read by the web server when no worker is running: give it the same
        // time a worker would have, rather than the web request's default.
        if (! app()->runningInConsole()) {
            @set_time_limit($this->timeout);
        }

        try {
            $result = $extractor->extract($this->upload->absolutePath(), $this->upload->mime_type);

            $payload = $this->groupByYear($result);

            $this->upload->update([
                'extracted_images' => $result->images,
                'ai_response' => $payload,
                'detected_years' => collect($payload['years'])->pluck('year')->unique()->values()->all(),
                'warnings' => array_values(array_filter([
                    $this->undatedWarning,
                    $result->looksScanned ? 'Parts of this document are scanned images with no selectable text, so they could not be read. Upload a typed (Word or text-based PDF) copy for those pages.' : null,
                    ...$result->warnings,
                ])),
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
        $fallbackYear = $this->fallbackYear($result);

        // One group per year and subject, in the order the years run. The
        // subject is the one the year's heading printed ("UTME 2010 USE OF
        // ENGLISH"), when it printed one.
        $grouped = collect($result->questions)
            ->groupBy(fn (array $question) => ((int) ($question['year'] ?? 0) ?: $fallbackYear).'|'.($question['subject'] ?? ''))
            ->map(fn ($questions, $key) => [
                'year' => (int) explode('|', (string) $key, 2)[0],
                'subject' => explode('|', (string) $key, 2)[1] ?: null,
                'instructions' => $result->instructions,

                // values() so the group is a list, and the order within a
                // paper is the order the document printed.
                'questions' => $questions->values()->all(),
            ])
            ->sortBy(fn (array $group) => [$group['year'], $group['subject']])
            ->values()
            ->all();

        $detectedSubject = collect($result->questions)->pluck('subject')->filter()->countBy()->sortDesc()->keys()->first();

        return [
            'exam_body' => (string) ($this->upload->examBody?->code ?? ''),
            'subject' => (string) ($this->upload->subject?->name ?? $detectedSubject ?? ''),
            'years' => $grouped,
        ];
    }

    /**
     * The year for questions the document never dates.
     *
     * In order: the year the uploader typed; the one year the file name
     * names ("JAMB Mathematics 2015.pdf"); the one year the document's
     * opening lines name. Only when none of those exists is it the year of
     * upload, and the upload then says so, because "2026" on a 2015 paper
     * is exactly the misfiling the reviewer has to catch.
     */
    private function fallbackYear(ExtractionResult $result): int
    {
        if ($this->upload->year) {
            return (int) $this->upload->year;
        }

        foreach ([(string) $this->upload->original_filename, (string) $result->instructions] as $source) {
            $years = self::yearsIn($source);

            if (count($years) === 1) {
                return $years[0];
            }
        }

        $undated = collect($result->questions)->contains(fn (array $question) => empty($question['year']));
        $year = (int) ($this->upload->created_at?->year ?? now()->year);

        if ($undated) {
            $this->undatedWarning = "Some questions carry no examination year in the document, so they were filed under {$year}. "
                .'If that is wrong, upload the document again with its Examination Year filled in.';
        }

        return $year;
    }

    /**
     * The distinct plausible examination years a piece of text names.
     *
     * @return list<int>
     */
    private static function yearsIn(string $text): array
    {
        preg_match_all('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $text, $m);

        return collect($m[1])
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year) => $year >= 1960 && $year <= now()->year + 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Take the upload, or decline if something else already has it.
     */
    private function claim(): bool
    {
        return DB::transaction(function (): bool {
            $fresh = CbtDocumentUpload::where('id', $this->upload->id)->lockForUpdate()->first();

            // Only a waiting upload is taken. A paper the web server has already
            // read must not be read again, and its questions replaced, by a job
            // a worker picks up later.
            if ($fresh === null || $fresh->status !== CbtDocumentUploadStatus::Pending) {
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

        return CbtDocumentImportService::matchSubject($name);
    }
}
