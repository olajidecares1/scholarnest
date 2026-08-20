<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CbtAttempt;
use App\Models\CbtQuestionOption;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CbtAttemptController extends Controller
{
    public function show(Request $request, School $school, CbtAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt);

        if ($attempt->isExpired()) {
            $this->finalize($attempt, autoSubmitted: true);
        }

        $attempt->load(['exam.subject', 'exam.examBody', 'answers']);

        if ($attempt->isSubmitted()) {
            return view('student.cbt-practice.result', [
                'school' => $school,
                'attempt' => $attempt,
                'questions' => $attempt->exam->questions()->with('options')->get(),
            ]);
        }

        return view('student.cbt-practice.take', [
            'school' => $school,
            'attempt' => $attempt,
            'questions' => $attempt->exam->questions()->with('options')->get(),
            'answeredMap' => $attempt->answers->mapWithKeys(fn ($answer) => [(string) $answer->cbt_question_id => $answer->cbt_question_option_id]),
        ]);
    }

    public function saveAnswer(Request $request, School $school, CbtAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        abort_if($attempt->isSubmitted(), 422, 'This attempt has already been submitted.');

        if ($attempt->isExpired()) {
            $this->finalize($attempt, autoSubmitted: true);

            return response()->json([
                'expired' => true,
                'redirect' => route('student.cbt-practice.attempts.show', [$school, $attempt]),
            ], 409);
        }

        $validated = $request->validate([
            'cbt_question_id' => ['required', 'integer', 'exists:cbt_questions,id'],
            'cbt_question_option_id' => ['nullable', 'integer', 'exists:cbt_question_options,id'],
        ]);

        $option = $validated['cbt_question_option_id'] ?? null
            ? CbtQuestionOption::find($validated['cbt_question_option_id'])
            : null;

        $attempt->answers()->updateOrCreate(
            ['cbt_question_id' => $validated['cbt_question_id']],
            ['cbt_question_option_id' => $option?->id, 'is_correct' => (bool) $option?->is_correct],
        );

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, School $school, CbtAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        if (! $attempt->isSubmitted()) {
            $this->finalize($attempt, autoSubmitted: false);
        }

        return redirect()->route('student.cbt-practice.attempts.show', [$school, $attempt]);
    }

    private function finalize(CbtAttempt $attempt, bool $autoSubmitted): void
    {
        $correctCount = $attempt->answers()->where('is_correct', true)->count();

        $attempt->update([
            'submitted_at' => now(),
            'auto_submitted' => $autoSubmitted,
            'score' => $attempt->total_questions > 0 ? round(($correctCount / $attempt->total_questions) * 100, 1) : 0,
        ]);
    }

    private function authorizeAttempt(Request $request, CbtAttempt $attempt): void
    {
        abort_unless($attempt->student_id === $request->user('student')->id, 403);
    }
}
