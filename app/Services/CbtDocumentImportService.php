<?php

namespace App\Services;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use Illuminate\Support\Facades\DB;

/**
 * Turns an upload's extracted questions into the catalogue: one CbtExam per
 * year (and per subject, when one document holds several), with each question
 * and its options as their own records.
 *
 * NOTHING IS PUBLISHED HERE. Every question read from a document waits for the
 * AkademicNest Team to review it on the upload page and publish it; until then
 * no student sees it.
 *
 * NOTHING IS DUPLICATED. Importing an upload again (a retry) first removes the
 * questions that same upload created, and a question whose wording and options
 * already exist in the same exam, from another upload or typed in by hand, is
 * skipped and counted rather than added a second time.
 *
 * Every question passes through {@see ExtractedQuestion} first, which is what
 * decides whether it is fit for a student to answer. Keeping that rule in one
 * place is what stops the catalogue and the school-test importer drifting
 * apart on it.
 */
class CbtDocumentImportService
{
    /**
     * @return array{extracted: int, needs_review: int, duplicates: int}
     */
    public function import(CbtDocumentUpload $upload, CbtExamBody $examBody, CbtSubject $subject): array
    {
        $data = $upload->ai_response ?? [];
        $images = $upload->extracted_images ?? [];
        $extractedCount = 0;
        $reviewCount = 0;
        $duplicates = 0;
        $warnings = [];

        DB::transaction(function () use ($upload, $examBody, $subject, $data, $images, &$extractedCount, &$reviewCount, &$duplicates, &$warnings) {
            // A retry replaces this upload's own questions, and only those.
            $previousExams = CbtQuestion::where('cbt_document_upload_id', $upload->id)->distinct()->pluck('cbt_exam_id');
            CbtQuestion::where('cbt_document_upload_id', $upload->id)->delete();

            foreach ($data['years'] ?? [] as $yearBlock) {
                $groupSubject = $this->subjectFor($yearBlock['subject'] ?? null, $subject);

                $exam = CbtExam::firstOrCreate(
                    [
                        'cbt_exam_body_id' => $examBody->id,
                        'cbt_subject_id' => $groupSubject->id,
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
                $label = trim($groupSubject->name.' '.$yearBlock['year']);
                array_push($warnings, ...$questions->warnings($label));

                $existing = $exam->questions()->whereNotNull('fingerprint')->pluck('fingerprint')->flip();
                $nextSortOrder = (int) $exam->questions()->max('sort_order') + 1;

                foreach ($questions->questions as $question) {
                    $fingerprint = $question->fingerprint();

                    if (isset($existing[$fingerprint])) {
                        $duplicates++;

                        continue;
                    }

                    $existing[$fingerprint] = true;

                    $record = CbtQuestion::create([
                        'cbt_exam_id' => $exam->id,
                        'cbt_document_upload_id' => $upload->id,
                        'question_text' => $question->text,
                        'question_number' => $question->number,
                        'passage' => $question->passage,
                        'explanation' => $question->explanation,
                        'fingerprint' => $fingerprint,
                        'image_path' => $question->imageIndex !== null ? ($images[$question->imageIndex] ?? null) : null,
                        'marks' => $question->marks,
                        'sort_order' => $nextSortOrder++,
                        'needs_review' => $question->needsReview(),
                        'review_notes' => $question->reviewNotes(),
                        'is_published' => false,
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

            if ($duplicates > 0) {
                $warnings[] = "{$duplicates} question(s) were already in the question bank and were skipped rather than added twice.";
            }

            // A paper the earlier reading filed wrongly (all nine years under
            // the year of upload, say) is left with nothing in it once this
            // reading files the questions properly. It goes, unless a student
            // has handed in an attempt at it.
            CbtExam::whereIn('id', $previousExams)
                ->whereDoesntHave('questions')
                ->whereDoesntHave('attempts', fn ($query) => $query->whereNotNull('submitted_at'))
                ->delete();

            $upload->update([
                'cbt_exam_body_id' => $examBody->id,
                'cbt_subject_id' => $subject->id,
                'status' => CbtDocumentUploadStatus::Completed,
                'questions_extracted_count' => $extractedCount,
                'questions_needing_review_count' => $reviewCount,
                'duplicates_skipped' => $duplicates,
                'warnings' => array_values(array_unique([...($upload->warnings ?? []), ...$warnings])),
                'published_at' => null,
                'processed_at' => now(),
            ]);
        });

        return ['extracted' => $extractedCount, 'needs_review' => $reviewCount, 'duplicates' => $duplicates];
    }

    /**
     * Make an upload's questions visible to students.
     *
     * Questions still marked as needing review are held back: they are
     * published once the AkademicNest Team has corrected them in the question
     * bank.
     *
     * @return array{published: int, held_back: int}
     */
    public function publish(CbtDocumentUpload $upload): array
    {
        $questions = CbtQuestion::where('cbt_document_upload_id', $upload->id);

        $published = (clone $questions)->where('needs_review', false)->update(['is_published' => true]);
        $heldBack = (clone $questions)->where('needs_review', true)->where('is_published', false)->count();

        $upload->update(['published_at' => now()]);

        return ['published' => $published, 'held_back' => $heldBack];
    }

    /**
     * The subject a year's questions belong to.
     *
     * The subject chosen for the upload, unless the document's own heading
     * names a different subject the catalogue already has, which is how one
     * document holding several subjects is filed correctly.
     */
    private function subjectFor(?string $detected, CbtSubject $chosen): CbtSubject
    {
        if (blank($detected) || self::sameSubject($detected, $chosen->name)) {
            return $chosen;
        }

        return self::matchSubject($detected) ?? $chosen;
    }

    /**
     * A catalogue subject for a name a document printed, allowing for the ways
     * the same subject is written: "Use of English" is English Language,
     * "LITERATURE-IN-ENGLISH" is Literature in English.
     */
    public static function matchSubject(?string $name): ?CbtSubject
    {
        if (blank($name)) {
            return null;
        }

        return CbtSubject::all()->first(fn (CbtSubject $subject) => self::sameSubject($name, $subject->name));
    }

    private static function sameSubject(string $a, string $b): bool
    {
        return self::subjectKey($a) === self::subjectKey($b);
    }

    private static function subjectKey(string $name): string
    {
        $key = preg_replace('/[^a-z]/', '', strtolower($name)) ?? '';

        return match ($key) {
            'useofenglish', 'english', 'englishlanguage' => 'englishlanguage',
            'literature', 'literatureinenglish', 'englishliterature' => 'literatureinenglish',
            'crk', 'crs', 'christianreligiousknowledge', 'christianreligiousstudies' => 'crk',
            'irk', 'irs', 'islamicreligiousknowledge', 'islamicstudies' => 'irk',
            'maths', 'math', 'mathematics', 'generalmathematics' => 'mathematics',
            'accounts', 'principlesofaccounts', 'financialaccounting', 'accounting' => 'accounts',
            default => $key,
        };
    }
}
