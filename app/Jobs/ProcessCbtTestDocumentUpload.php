<?php

namespace App\Jobs;

use App\Enums\CbtDocumentUploadStatus;
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
 * Extraction happens locally - PHPWord for .docx, smalot/pdfparser for .pdf,
 * and a PHP parser for the questions themselves. Nothing here reaches the
 * network, so an unpaid API bill or an outage cannot stop a teacher preparing
 * a test.
 */
class ProcessCbtTestDocumentUpload implements ShouldQueue
{
    use Queueable;

    /**
     * Local parsing is fast, but a 20MB scanned PDF still takes a while to open
     * and a very long paper takes a while to walk.
     */
    public int $timeout = 300;

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
     * document somebody uploaded and then removed - while nothing was working
     * the queue - left a job pointing at a row that no longer exists. It threw
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
            // questions in it - it is the wrong file, or one whose layout the
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

            $this->import($questions);
        } catch (Throwable $e) {
            // The teacher gets a sentence they can act on; the detail goes to
            // the log for whoever maintains the server.
            Log::error('CBT test document extraction failed', [
                'upload_id' => $this->upload->id,
                'exception' => $e,
            ]);

            $this->fail(
                'Document extraction failed. Please check the document format and try again. '
                .'The file is stored safely — you do not need to upload it again.'
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
     */
    private function import(ExtractedQuestionSet $questions): void
    {
        DB::transaction(function () use ($questions): void {
            $test = $this->upload->test;

            CbtTestQuestion::where('cbt_test_document_upload_id', $this->upload->id)->delete();

            $sortOrder = $test->questions()->count();

            foreach ($questions->questions as $question) {
                $record = CbtTestQuestion::create([
                    'cbt_test_id' => $test->id,
                    'cbt_test_document_upload_id' => $this->upload->id,
                    'question_text' => $question->text,
                    'marks' => $question->marks,
                    'sort_order' => $sortOrder++,
                    'needs_review' => $question->needsReview(),
                    'review_notes' => $question->reviewNotes(),
                ]);

                foreach ($question->options as $option) {
                    $record->options()->create([
                        'label' => $option['label'],
                        'option_text' => $option['text'],
                        'is_correct' => $option['is_correct'],
                    ]);
                }
            }

            $this->upload->update([
                'status' => CbtDocumentUploadStatus::Completed,
                'error_message' => null,
                'questions_extracted_count' => $questions->count(),
                'questions_needing_review_count' => $questions->needingReviewCount(),
                'processed_at' => now(),
            ]);
        });
    }
}
