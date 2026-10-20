<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Services\ManualAttendanceSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The School Admin's view of manual attendance: every class, and the right to
 * correct any figure, including one already on a published card.
 */
class ManualAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $classes = collect($school->configuredClassNames());
        $requested = $request->string('class')->toString();
        $className = $requested && $classes->contains($requested) ? $requested : $classes->first();
        [$session, $term] = ManualAttendanceSheet::periodFrom($request, $school);

        $students = $className && $school->manual_attendance_enabled
            ? $school->students()->where('is_active', true)->where('class_name', $className)->orderBy('last_name')->get()
            : collect();

        return view('school-admin.attendance.manual', [
            'school' => $school,
            'classes' => $classes,
            'className' => $className,
            'session' => $session,
            'term' => $term,
            'academicTerm' => AcademicTerm::rangeFor($school, $session, $term),
            'rows' => ManualAttendanceSheet::rows($school, $students, $session, $term, $request->boolean('prefill')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        abort_unless($school->manual_attendance_enabled, 403);

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($school->configuredClassNames())],
            ...ManualAttendanceSheet::rules(),
        ]);

        $term = ExamTerm::from($validated['term']);

        $students = $school->students()
            ->where('class_name', $validated['class_name'])
            ->whereIn('id', array_keys($validated['entries']))
            ->get()
            ->keyBy('id');

        $result = ManualAttendanceSheet::save($school, $students, $validated['session'], $term, $validated['entries'], user: $request->user());

        return redirect()
            ->route('attendance.manual', ['class' => $validated['class_name'], 'session' => $validated['session'], 'term' => $term->value])
            ->with('status', ManualAttendanceSheet::statusMessage($result, $term, $validated['session']));
    }
}
