<?php

namespace App\Http\Controllers\Student;

use App\Enums\CbtTestStatus;
use App\Http\Controllers\Controller;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolTestController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $tests = CbtTest::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->whereIn('status', [CbtTestStatus::Published, CbtTestStatus::Archived])
            ->withCount('questions')
            ->with(['attempts' => fn ($query) => $query->where('student_id', $student->id)])
            ->latest()
            ->get()
            ->filter(fn (CbtTest $test) => $test->isOpenForStudents() || $test->attempts->isNotEmpty());

        return view('student.tests.index', [
            'school' => $school,
            'tests' => $tests,
        ]);
    }

    public function start(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $student = $request->user('student');

        abort_unless($test->school_id === $student->school_id && $test->class_name === $student->class_name, 403);
        abort_unless($test->isOpenForStudents(), 403, 'This test is not currently open.');

        $attempt = CbtTestAttempt::firstOrCreate(
            ['student_id' => $student->id, 'cbt_test_id' => $test->id],
            [
                'started_at' => now(),
                'expires_at' => now()->addMinutes($test->duration_minutes),
                'total_questions' => $test->questions()->count(),
            ]
        );

        return redirect()->route('student.tests.attempts.show', [$school, $attempt]);
    }
}
