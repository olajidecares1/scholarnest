<?php

namespace App\Jobs;

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Services\ExtractedQuestionSet;
use App\Services\Uploads\UploadStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turn a teacher's uploaded question paper into CBT questions.
 *
 * Extraction happens locally, PHPWord for .docx, smalot/pdfparser for .pdf,
 * and a PHP parser for the questions themselves. Nothing here reaches the
 * network, so an unpaid API bill or an outage cannot stop a teacher preparing
 * a test.
 */
class ProcessCbtTestDocumentUpload implements ShouldQueue
{
    use Queueable;

    /**
     * Local parsing is fast, but a 20MB scanned PDF still takes a while to open
     * and a long two-column paper has to be read position by position to come
     * out in printed order, twice, so the better reading can be kept.
     */
    public int $timeout = 1800;

    /**
     * Retried, because the failures worth retrying are transient: a locked
     * file, a moment of disk trouble. A malformed document fails the same way
     * every time and is caught below rather than thrown, so it does not burn
     * attempts.
     */
    public int $tries = 3;

    /**
     * An upload deleted before its job ran is not a failure.
     *
     * The job holds the model by id and looks it up again when it runs, so a
     * document somebody uploaded and then removed, while nothing was working
     * the queue, left a job pointing at a row that no longer exists. It threw
     * ModelNotFoundException and landed in failed_jobs, which is a real alarm
     * raised over somebody changing their mind.
     *
     * With this, the job is quietly dropped instead. There is genuinely
     * nothing to do: the record is gone, and so is the file it named.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(public CbtTestDocumentUpload $upload) {}

    public function handle(QuestionExtractionProvider $extractor): void
    {
        // Claiming the row is what stops a double-click on "Try extracting
        // again" importing the same paper twice: the second job finds the
        // upload already moving and stops.
        if (! $this->claim()) {
            return;
        }

        // Read by the web server when no worker is running: give it the same
        // time a worker would have, rather than the web request's default.
        if (! app()->runningInConsole()) {
            @set_time_limit($this->timeout);
        }

        try {
            $test = $this->upload->test;

            $result = $extractor->extract($this->upload->absolutePath(), $this->upload->mime_type);

            $this->upload->update([
                'extracted_images' => $result->images,
                'ai_response' => $result->toPayload(),
            ]);

            if ($result->isEmpty()) {
                $this->fail($result->failureReason());

                return;
            }

            $questions = ExtractedQuestionSet::fromExtraction($result->toPayload());

            // A rubric the teacher already wrote wins; the document only
            // fills a gap.
            if (filled($result->instructions) && blank($test->instructions)) {
                $test->update(['instructions' => $result->instructions]);
            }

            // A document that mostly failed to read is not a test with some bad
            // questions in it, it is the wrong file, or one whose layout the
            // extractor could not follow. Importing it would hand a teacher a
            // CBT that looks finished and is not.
            if (! $questions->isAcceptable()) {
                $this->fail((string) $questions->rejectionReason());

                return;
            }

            // Reading is done; building the questions is a separate stretch
            // and is named separately, because "Processing" for a solid
            // minute tells the person watching nothing about progress.
            $this->upload->update(['status' => CbtDocumentUploadStatus::Importing]);

            $this->import($result->toPayload());
        } catch (Throwable $e) {
            // The teacher gets a sentence they can act on; the detail goes to
            // the log for whoever maintains the server.
            Log::error('CBT test document extraction failed', [
                'upload_id' => $this->upload->id,
                'exception' => $e,
            ]);

            $this->fail(
                'Document extraction failed. Please check the document format and try again. '
                .'The file is stored safely, so you do not need to upload it again.'
            );
        } finally {
            // A worker does not end between jobs, so the temporary local copy
            // of a document held in object storage is removed here.
            UploadStorage::releaseLocalCopies();
        }
    }

    /**
     * Take the upload, or decline if something else already has it.
     *
     * Locked and re-read inside the transaction, so two jobs racing on the same
     * upload cannot both pass the check.
     */
    private function claim(): bool
    {
        return DB::transaction(function (): bool {
            $fresh = CbtTestDocumentUpload::where('id', $this->upload->id)->lockForUpdate()->first();

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
            'questions_extracted_count' => 0,
            'questions_needing_review_count' => 0,
        ]);
    }

    /**
     * Write the questions, replacing anything this upload produced before.
     *
     * Deleting this upload's own previous questions is what makes "Try
     * extracting again" safe to press twice: a retry replaces its own work
     * rather than adding a second copy of every question. Questions the teacher
     * typed by hand, and those from other uploads, are keyed to a different
     * upload id and are untouched.
     *
     * ONE TEST PER YEAR. A past-question compilation holds several papers, and
     * nobody sits 430 questions from nine years as one examination. When the
     * document names more than one year, the test it was uploaded into takes
     * the first year and each further year becomes a test of its own, with the
     * same class, subject, timing and pass mark, left as a draft for the
     * teacher to publish. Reading the document again refills those same tests.
     *
     * @param  array<string, mixed>  $payload
     */
    private function import(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $test = $this->upload->test;
            $groups = $this->yearsOf($payload['questions'] ?? []);
            $targets = $this->testsFor($test, array_keys($groups));

            CbtTestQuestion::where('cbt_test_document_upload_id', $this->upload->id)->delete();

            // A student who opened one of these tests and answered nothing has
            // an attempt built on the questions just removed. It is cleared so
            // they start again on the paper as it now reads. Attempts with
            // answers never reach here: reading again is refused for those.
            CbtTestAttempt::whereIn('cbt_test_id', collect($targets)->pluck('id'))
                ->whereNull('submitted_at')
                ->whereDoesntHave('answers')
                ->delete();

            $images = $this->upload->extracted_images ?? [];
            $split = count($groups) > 1;
            $warnings = [];
            $duplicates = 0;
            $imported = 0;
            $needingReview = 0;

            foreach ($groups as $year => $raw) {
                $target = $targets[$year];
                $questions = ExtractedQuestionSet::fromExtraction(['questions' => $raw]);
                array_push($warnings, ...$questions->warnings($split ? (string) $year : ''));

                $sortOrder = $target->questions()->count();

                // A question already in the test, from another upload of the
                // same paper or typed in by the teacher, is not added again.
                $existing = $target->questions()->whereNotNull('fingerprint')->pluck('fingerprint')->flip();

                foreach ($questions->questions as $question) {
                    $fingerprint = $question->fingerprint();

                    if (isset($existing[$fingerprint])) {
                        $duplicates++;

                        continue;
                    }

                    $existing[$fingerprint] = true;

                    $record = CbtTestQuestion::create([
                        'cbt_test_id' => $target->id,
                        'cbt_test_document_upload_id' => $this->upload->id,
                        'question_text' => $question->text,
                        'question_number' => $question->number,
                        'passage' => $question->passage,
                        'explanation' => $question->explanation,
                        'fingerprint' => $fingerprint,
                        'image_path' => $question->imageIndex !== null ? ($images[$question->imageIndex] ?? null) : null,
                        'marks' => $question->marks,
                        'sort_order' => $sortOrder++,
                        'needs_review' => $question->needsReview(),
                        'review_notes' => $question->reviewNotes(),
                    ]);

                    $imported++;

                    if ($record->needs_review) {
                        $needingReview++;
                    }

                    foreach ($question->options as $option) {
                        $record->options()->create([
                            'label' => $option['label'],
                            'option_text' => $option['text'],
                            'is_correct' => $option['is_correct'],
                        ]);
                    }
                }
            }

            $this->removeUnusedYearTests(array_keys($groups));

            if ($duplicates > 0) {
                $warnings[] = "{$duplicates} question(s) were already in the test and were skipped rather than added twice.";
            }

            $this->upload->update([
                'status' => CbtDocumentUploadStatus::Completed,
                'error_message' => null,
                'questions_extracted_count' => $imported,
                'questions_needing_review_count' => $needingReview,
                'duplicates_skipped' => $duplicates,
                'warnings' => $warnings,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * The questions, one group per year the document names, earliest first.
     *
     * A document with no years, or only one, is a single group. Questions
     * printed before the first year heading belong with the first year.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<int, list<array<string, mixed>>> keyed by year, or by 0 for no year
     */
    private function yearsOf(array $questions): array
    {
        $years = collect($questions)->pluck('year')->filter()->map(fn ($year) => (int) $year)->unique()->sort()->values();

        if ($years->count() < 2) {
            return [0 => array_values($questions)];
        }

        $groups = array_fill_keys($years->all(), []);

        foreach ($questions as $question) {
            $groups[(int) ($question['year'] ?? 0) ?: $years->first()][] = $question;
        }

        return $groups;
    }

    /**
     * The test each year's questions go into.
     *
     * @param  list<int>  $years
     * @return array<int, CbtTest> keyed like the groups
     */
    private function testsFor(CbtTest $test, array $years): array
    {
        if ($years === [0]) {
            return [0 => $test];
        }

        $base = $this->baseTitle($test);
        $first = array_shift($years);

        if ($test->source_year !== $first) {
            $test->update([
                'title' => "{$base} — {$first}",
                'source_year' => $first,
            ]);
        }

        $targets = [$first => $test];

        foreach ($years as $year) {
            $targets[$year] = CbtTest::firstOrCreate(
                ['source_upload_id' => $this->upload->id, 'source_year' => $year],
                [
                    ...$test->only([
                        'school_id', 'staff_id', 'instructions', 'subject', 'class_name', 'session',
                        'duration_minutes', 'pass_mark', 'shuffle_questions',
                    ]),
                    'title' => "{$base} — {$year}",
                    'status' => CbtTestStatus::Draft,
                ],
            );
        }

        return $targets;
    }

    /**
     * The test's name without the year this importer added to it before.
     */
    private function baseTitle(CbtTest $test): string
    {
        return $test->source_year === null
            ? $test->title
            : (string) preg_replace('/\s+—\s+'.$test->source_year.'$/u', '', $test->title);
    }

    /**
     * Year tests from an earlier reading whose year this reading did not find,
     * as long as nothing has been put in them since and no student has
     * started them.
     *
     * @param  list<int>  $years
     */
    private function removeUnusedYearTests(array $years): void
    {
        CbtTest::where('source_upload_id', $this->upload->id)
            ->whereNotIn('source_year', $years)
            ->whereDoesntHave('questions')
            ->whereDoesntHave('attempts')
            ->delete();
    }
}
