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
     * Turn a document upload's extracted AI response into real CbtExam /
     * CbtQuestion / CbtQuestionOption records, one CbtExam per detected year.
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

                $nextSortOrder = $exam->questions()->count();

                foreach ($yearBlock['questions'] ?? [] as $questionData) {
                    $answerMissing = ($questionData['answer_source'] ?? 'not_found') !== 'found_in_document'
                        || empty($questionData['correct_label']);
                    $hasDiagram = (bool) ($questionData['has_diagram'] ?? false);

                    $question = CbtQuestion::create([
                        'cbt_exam_id' => $exam->id,
                        'cbt_document_upload_id' => $upload->id,
                        'question_text' => $questionData['question_text'] ?? '',
                        'sort_order' => $nextSortOrder++,
                        'needs_review' => $answerMissing || $hasDiagram,
                        'review_notes' => $this->reviewNotes($questionData, $answerMissing, $hasDiagram),
                    ]);

                    foreach ($questionData['options'] ?? [] as $option) {
                        $label = strtoupper((string) ($option['label'] ?? ''));

                        $question->options()->create([
                            'label' => $label,
                            'option_text' => $option['text'] ?? '',
                            'is_correct' => ! $answerMissing && strtoupper((string) $questionData['correct_label']) === $label,
                        ]);
                    }

                    $extractedCount++;

                    if ($question->needs_review) {
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

    /**
     * @param  array<string, mixed>  $questionData
     */
    private function reviewNotes(array $questionData, bool $answerMissing, bool $hasDiagram): ?string
    {
        $notes = [];

        if ($answerMissing) {
            $notes[] = 'Correct answer was not found in the source document - select it manually.';
        }

        if ($hasDiagram) {
            $description = $questionData['diagram_description'] ?? null;
            $notes[] = $description
                ? "Diagram referenced ({$description}) - attach the matching image manually."
                : 'Diagram referenced - attach the matching image manually.';
        }

        return $notes === [] ? null : implode(' ', $notes);
    }
}
