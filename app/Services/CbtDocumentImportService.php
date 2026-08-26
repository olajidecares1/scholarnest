<?php

namespace App\Services;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use Illuminate\Support\Facades\DB;

class CbtDocumentImportService
{
    /**
     * Turn a document upload's extracted response into real CbtExam /
     * CbtQuestion / CbtQuestionOption records, one CbtExam per detected year.
     *
     * Every question passes through {@see ExtractedQuestion} first, which is
     * what decides whether it is fit for a student to answer. This service used
     * to make that judgement inline and got it wrong in one specific way: an
     * answer key naming an option the question did not have produced a question
     * with no correct option and no review flag, so it published looking normal
     * and marked every student wrong. Keeping the rule in one place is what
     * stops the catalog and the school-test importer drifting apart on it.
     *
     * @return array{extracted: int, needs_review: int}
     */
    public function import(CbtDocumentUpload $upload, CbtExamBody $examBody, CbtSubject $subject): array
    {
        $data = $upload->ai_response ?? [];
        $extractedCount = 0;
        $reviewCount = 0;

        DB::transaction(function () use ($upload, $examBody, $subject, $data, &$extractedCount, &$reviewCount) {
            foreach ($data['years'] ?? [] as $yearBlock) {
                $exam = CbtExam::firstOrCreate(
                    [
                        'cbt_exam_body_id' => $examBody->id,
                        'cbt_subject_id' => $subject->id,
                        'year' => $yearBlock['year'],
                    ],
                    [
                        'duration_minutes' => 60,
                        'pass_mark' => 50,
                        'created_by' => $upload->uploaded_by,
                    ]
                );

                if (filled($yearBlock['instructions'] ?? null) && blank($exam->instructions)) {
                    $exam->update(['instructions' => $yearBlock['instructions']]);
                }

                $questions = ExtractedQuestionSet::fromExtraction($yearBlock);
                $nextSortOrder = $exam->questions()->count();

                foreach ($questions->questions as $question) {
                    $record = CbtQuestion::create([
                        'cbt_exam_id' => $exam->id,
                        'cbt_document_upload_id' => $upload->id,
                        'question_text' => $question->text,
                        'marks' => $question->marks,
                        'sort_order' => $nextSortOrder++,
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

                    $extractedCount++;

                    if ($record->needs_review) {
                        $reviewCount++;
                    }
                }
            }

            $upload->update([
                'cbt_exam_body_id' => $examBody->id,
                'cbt_subject_id' => $subject->id,
                'status' => CbtDocumentUploadStatus::Completed,
                'questions_extracted_count' => $extractedCount,
                'questions_needing_review_count' => $reviewCount,
                'processed_at' => now(),
            ]);
        });

        return ['extracted' => $extractedCount, 'needs_review' => $reviewCount];
    }
}
