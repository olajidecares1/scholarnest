<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CbtExam;
use App\Models\CbtQuestion;
use App\Services\ImageOptimizer;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CbtQuestionController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function store(Request $request, CbtExam $exam): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $exam, $request) {
            $question = $exam->questions()->create([
                'question_text' => $validated['question_text'],
                'image_path' => $this->storeImage($request),
                'sort_order' => $exam->questions()->count(),
            ]);

            $this->syncOptions($question, $validated);
        });

        AuditLog::record('cbt.question.created', "Added a question to \"{$exam->title()}\".", $exam);

        return back()->with('status', 'Question added successfully.');
    }

    public function update(Request $request, CbtQuestion $question): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $question, $request) {
            $newImage = $this->storeImage($request);

            // Saving the form is the review - it requires a correct answer,
            // so whatever extraction left unresolved has now been settled.
            $question->update([
                'question_text' => $validated['question_text'],
                'image_path' => $newImage ?: $question->image_path,
                'needs_review' => false,
                'review_notes' => null,
            ]);

            $question->options()->delete();
            $this->syncOptions($question, $validated);
        });

        $exam = $question->exam;
        AuditLog::record('cbt.question.updated', "Updated a question in \"{$exam->title()}\".", $exam);

        return back()->with('status', 'Question updated successfully.');
    }

    public function destroy(CbtQuestion $question): RedirectResponse
    {
        $exam = $question->exam;

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

        AuditLog::record('cbt.question.deleted', "Deleted a question from \"{$exam->title()}\".", $exam);

        return back()->with('status', 'Question deleted successfully.');
    }

    /**
     * @return array{question_text: string, options: list<string>, correct_index: int}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question_text' => ['required', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'options' => ['required', 'array', 'min:2', 'max:5'],
            'options.*' => ['required', 'string', 'max:1000'],
            'correct_index' => [
                'required',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ((int) $value >= count($request->input('options', []))) {
                        $fail('Select which option is the correct answer.');
                    }
                },
            ],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $path = $file->storeAs('cbt-questions', StoredUpload::name($file), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    /**
     * @param  array{options: list<string>, correct_index: int}  $validated
     */
    private function syncOptions(CbtQuestion $question, array $validated): void
    {
        $labels = ['A', 'B', 'C', 'D', 'E'];

        foreach (array_values($validated['options']) as $index => $text) {
            $question->options()->create([
                'label' => $labels[$index],
                'option_text' => $text,
                'is_correct' => $index === (int) $validated['correct_index'],
            ]);
        }
    }
}
