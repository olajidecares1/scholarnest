<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Services\ResultArchive;
use App\Services\SessionResultCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The School Admin's way into every result the school has saved.
 *
 *   - ALL RESULTS: every session, then each term, then each class, with how
 *     many students have results there, one click from the class's results.
 *   - ONE STUDENT, by admission number: every term they have ever sat, in
 *     the class they sat it in, with each session's cumulative worked out
 *     from those same saved results.
 *
 * Read only. Results are saved where the marks are entered; this only finds
 * them again. Everything is scoped to the signed-in admin's own school.
 */
class ResultArchiveController extends Controller
{
    public function __construct(
        private readonly ResultArchive $archive,
        private readonly SessionResultCalculator $sessions,
    ) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.results.archive', [
            'overview' => $this->archive->overview($school),
            'currentSession' => $school->currentSession(),
        ]);
    }

    /**
     * One student's whole record, found by admission number.
     */
    public function student(Request $request): View|RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'admission_number' => ['required', 'string', 'max:100'],
        ], [
            'admission_number.required' => 'Enter an admission number.',
        ]);

        $student = $this->sessions->studentByAdmissionNumber($school, $validated['admission_number']);

        if ($student === null) {
            return redirect()
                ->route('results.archive')
                ->withInput()
                ->withErrors(['admission_number' => "No student in your school has the admission number \"{$validated['admission_number']}\"."]);
        }

        $history = $this->archive->historyFor($student);

        // Each session's cumulative, matched on the admission number and
        // covering every term saved so far that session. Only for a school
        // that uses cumulative results.
        $cumulative = $school->usesCumulativeResults()
            ? $history->mapWithKeys(fn (array $terms, string $session) => [
                $session => $this->sessions->forAdmissionNumber(
                    $school,
                    (string) $student->admission_number,
                    $session,
                    end($terms)['term'],
                ),
            ])->filter()
            : collect();

        return view('school-admin.results.student', [
            'student' => $student,
            'history' => $history,
            'cumulative' => $cumulative,
            'cumulativeEnabled' => $school->usesCumulativeResults(),
            'termOptions' => ExamTerm::cases(),
        ]);
    }
}
