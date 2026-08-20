<?php

namespace App\Jobs;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Services\CbtDocxTextExtractor;
use App\Services\CbtTestDocumentExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCbtTestDocumentUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public CbtTestDocumentUpload $upload) {}

    public function handle(
        CbtTestDocumentExtractionService $extractor,
        CbtDocxTextExtractor $docxExtractor,
    ): void {
        $this->upload->update(['status' => CbtDocumentUploadStatus::Processing]);

        try {
            $test = $this->upload->test;

            if ($this->upload->mime_type === 'application/pdf') {
                $data = $extractor->extractFromPdf($this->upload->absolutePath(), $test->subject, $test->class_name);
            } else {
                $docx = $docxExtractor->extract($this->upload->absolutePath());
                $data = $extractor->extractFromText($docx['text'], $test->subject, $test->class_name);
                $this->upload->update(['extracted_images' => $docx['images']]);
            }

            $this->upload->update(['ai_response' => $data]);

            $this->import($data);
        } catch (Throwable $e) {
            $this->upload->update([
                'status' => CbtDocumentUploadStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{questions: list<array<string, mixed>>}  $data
     */
    private function import(array $data): void
    {
        $extractedCount = 0;
        $reviewCount = 0;

        DB::transaction(function () use ($data, &$extractedCount, &$reviewCount) {
            $test = $this->upload->test;
            $nextSortOrder = $test->questions()->count();

            foreach ($data['questions'] ?? [] as $questionData) {
                $answerMissing = ($questionData['answer_source'] ?? 'not_found') !== 'found_in_document'
                    || empty($questionData['correct_label']);
                $hasDiagram = (bool) ($questionData['has_diagram'] ?? false);

                $question = CbtTestQuestion::create([
                    'cbt_test_id' => $test->id,
                    'cbt_test_document_upload_id' => $this->upload->id,
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

            $this->upload->update([
                'status' => CbtDocumentUploadStatus::Completed,
                'questions_extracted_count' => $extractedCount,
                'questions_needing_review_count' => $reviewCount,
                'processed_at' => now(),
            ]);
        });
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
