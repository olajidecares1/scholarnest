<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestQuestionOption;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolTestAttemptController extends Controller
{
    public function show(Request $request, School $school, CbtTestAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->isExpired()) {
            $this->finalize($attempt, autoSubmitted: true);
        }

        $attempt->load(['test', 'answers']);

        if ($attempt->isSubmitted()) {
            return view('student.tests.result', [
                'school' => $school,
                'attempt' => $attempt,
                'questions' => $attempt->test->questions()->with('options')->get(),
            ]);
        }

        return view('student.tests.take', [
            'school' => $school,
            'attempt' => $attempt,
            'questions' => $attempt->test->questions()->with('options')->get(),
            'answeredMap' => $attempt->answers->mapWithKeys(fn ($answer) => [(string) $answer->cbt_test_question_id => $answer->cbt_test_question_option_id]),
        ]);
    }

    public function saveAnswer(Request $request, School $school, CbtTestAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        abort_if($attempt->isSubmitted(), 422, 'This attempt has already been submitted.');

        if ($attempt->isExpired()) {
            $this->finalize($attempt, autoSubmitted: true);

            return response()->json([
                'expired' => true,
                'redirect' => route('student.tests.attempts.show', [$school, $attempt]),
            ], 409);
        }

        $validated = $request->validate([
            'cbt_test_question_id' => ['required', 'integer', 'exists:cbt_test_questions,id'],
            'cbt_test_question_option_id' => ['nullable', 'integer', 'exists:cbt_test_question_options,id'],
        ]);

        $option = $validated['cbt_test_question_option_id'] ?? null
            ? CbtTestQuestionOption::find($validated['cbt_test_question_option_id'])
            : null;

        $attempt->answers()->updateOrCreate(
            ['cbt_test_question_id' => $validated['cbt_test_question_id']],
            ['cbt_test_question_option_id' => $option?->id, 'is_correct' => (bool) $option?->is_correct],
        );

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, School $school, CbtTestAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if (! $attempt->isSubmitted()) {
            $this->finalize($attempt, autoSubmitted: false);
        }

        return redirect()->route('student.tests.attempts.show', [$school, $attempt]);
    }

    /**
     * Mark the attempt and close it.
     *
     * Scored by marks rather than by question count, because a paper that says
     * a question is worth three carries that weight through to the result. Every
     * question defaults to one mark, so a test whose paper never stated marks
     * scores exactly as it always did.
     */
    private function finalize(CbtTestAttempt $attempt, bool $autoSubmitted): void
    {
        $marksByQuestion = $attempt->test->questions()->pluck('marks', 'id');
        $totalMarks = (int) $marksByQuestion->sum();

        $earned = $attempt->answers()
            ->where('is_correct', true)
            ->pluck('cbt_test_question_id')
            ->sum(fn ($questionId) => (int) ($marksByQuestion[$questionId] ?? 0));

        $attempt->update([
            'submitted_at' => now(),
            'auto_submitted' => $autoSubmitted,
            'score' => $totalMarks > 0 ? round(($earned / $totalMarks) * 100, 1) : 0,
        ]);
    }

    private function authorizeAttempt(Request $request, CbtTestAttempt $attempt): void
    {
        abort_unless($attempt->student_id === $request->user('student')->id, 403);
    }
}
