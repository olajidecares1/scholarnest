<?php

namespace App\Http\Controllers\Staff\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtTest;
use App\Models\CbtTestQuestion;
use App\Models\School;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function store(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $test, $request) {
            $question = $test->questions()->create([
                'question_text' => $validated['question_text'],
                'image_path' => $this->storeImage($request),
                'sort_order' => $test->questions()->count(),
            ]);

            $this->syncOptions($question, $validated);
        });

        return back()->with('status', 'Question added successfully.');
    }

    public function update(Request $request, School $school, CbtTest $test, CbtTestQuestion $question): RedirectResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($question->cbt_test_id === $test->id, 403);

        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $question, $request) {
            $newImage = $this->storeImage($request);

            $question->update([
                'question_text' => $validated['question_text'],
                'image_path' => $newImage ?: $question->image_path,
            ]);

            $question->options()->delete();
            $this->syncOptions($question, $validated);
        });

        return back()->with('status', 'Question updated successfully.');
    }

    public function destroy(Request $request, School $school, CbtTest $test, CbtTestQuestion $question): RedirectResponse
    {
        $this->authorizeTest($request, $test);
        abort_unless($question->cbt_test_id === $test->id, 403);

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

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
        $path = $file->storeAs('cbt-test-questions', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    /**
     * @param  array{options: list<string>, correct_index: int}  $validated
     */
    private function syncOptions(CbtTestQuestion $question, array $validated): void
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

    private function authorizeTest(Request $request, CbtTest $test): void
    {
        abort_unless($test->staff_id === $request->user('staff')->id, 403);
    }
}
