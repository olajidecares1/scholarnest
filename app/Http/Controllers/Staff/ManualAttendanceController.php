<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\School;
use App\Services\ManualAttendanceSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A class teacher entering a term's attendance as totals per student.
 *
 * Optional, switched on per school in Settings, and on every plan, like the
 * daily register beside it, which it never touches.
 */
class ManualAttendanceController extends Controller
{
    public function index(Request $request, School $school): View|RedirectResponse
    {
        if (! $school->manual_attendance_enabled) {
            return redirect()->route('staff.attendance.index', $school)
                ->with('status', 'Manual attendance entry is not switched on for your school. Ask your School Admin to enable it under Settings.');
        }

        $classes = $request->user('staff')->classesAsClassTeacher();
        $className = $this->resolveClass($request, $classes);
        [$session, $term] = ManualAttendanceSheet::periodFrom($request, $school);

        $students = $className
            ? $school->students()->where('is_active', true)->where('class_name', $className)->orderBy('last_name')->get()
            : collect();

        return view('staff.attendance.manual', [
            'school' => $school,
            'classes' => $classes,
            'className' => $className,
            'session' => $session,
            'term' => $term,
            'academicTerm' => AcademicTerm::rangeFor($school, $session, $term),
            'rows' => ManualAttendanceSheet::rows($school, $students, $session, $term, $request->boolean('prefill')),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        abort_unless($school->manual_attendance_enabled, 403);

        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes)],
            ...ManualAttendanceSheet::rules(),
        ]);

        $term = ExamTerm::from($validated['term']);

        // Scoped to the teacher's own class: ids from any other class in the
        // request are simply not found here, and so never written.
        $students = $school->students()
            ->where('class_name', $validated['class_name'])
            ->whereIn('id', array_keys($validated['entries']))
            ->get()
            ->keyBy('id');

        $result = ManualAttendanceSheet::save($school, $students, $validated['session'], $term, $validated['entries'], staff: $staff, respectLocks: true);

        return redirect()
            ->route('staff.attendance.manual', [$school, 'class' => $validated['class_name'], 'session' => $validated['session'], 'term' => $term->value])
            ->with('status', ManualAttendanceSheet::statusMessage($result, $term, $validated['session']));
    }

    /**
     * @param  Collection<int, string>  $classes
     */
    private function resolveClass(Request $request, Collection $classes): ?string
    {
        $requested = $request->string('class')->toString();

        return $requested && $classes->contains($requested) ? $requested : $classes->first();
    }
}
