<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\School;
use App\Services\Attendance\TermAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Term attendance entered as day counts, from the class teacher's side.
 *
 * Only for a school that has turned manual attendance on, and only for the
 * classes the teacher is Class Teacher of: the same boundary the daily
 * register keeps. On every plan.
 */
class TermAttendanceController extends Controller
{
    public function __construct(private readonly TermAttendance $attendance) {}

    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();
        $className = $this->resolveClass($request, $classes);
        [$session, $term] = $this->attendance->resolvePeriod($school, $request->input('session'), $request->input('term'));
        $academicTerm = $this->attendance->termFor($school, $session, $term);

        $students = $className && $school->usesManualAttendance()
            ? $school->students()->where('is_active', true)->where('class_name', $className)->alphabetical()->get()
            : collect();

        return view('staff.attendance.term', [
            'school' => $school,
            'classes' => $classes,
            'className' => $className,
            'session' => $session,
            'term' => $term,
            'sessionOptions' => $this->attendance->sessionOptions($school),
            'termOptions' => ExamTerm::cases(),
            'academicTerm' => $academicTerm,
            'expectedDays' => $academicTerm?->schoolDays(),
            'rows' => $this->attendance->rows($school, $students, $session, $term, $academicTerm),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        // Off at this school: the page says so, and the form is not there to
        // send. A request that arrives anyway is refused rather than saved
        // into a table the school is not using.
        abort_unless($school->usesManualAttendance(), 403, 'Manual term attendance is not turned on at this school.');

        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes)],
            'session' => ['required', 'string', Rule::in($this->attendance->sessionOptions($school))],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'rows' => ['required', 'array'],
            ...self::rowRules(),
        ]);

        $term = ExamTerm::from($validated['term']);

        // Only pupils in the teacher's own class can be written, however the
        // request was put together.
        $students = $school->students()
            ->where('class_name', $validated['class_name'])
            ->whereIn('id', array_keys($validated['rows']))
            ->get()
            ->keyBy('id');

        $result = $this->attendance->save(
            $school,
            $students,
            $validated['rows'],
            $validated['session'],
            $term,
            staff: $staff,
            mayOverwrite: $school->allowsTeacherAttendanceCorrections(),
            className: $validated['class_name'],
        );

        return redirect()
            ->route('staff.attendance.term.index', [$school, 'class' => $validated['class_name'], 'session' => $validated['session'], 'term' => $term->value])
            ->with('status', $this->attendance->savedMessage($result, $term, $validated['session']));
    }

    /**
     * The per-row rules both portals validate with.
     *
     * @return array<string, list<string>>
     */
    public static function rowRules(): array
    {
        $rules = ['rows.*' => ['array'], 'rows.*.notes' => ['nullable', 'string', 'max:255']];

        foreach (AttendanceSummary::COUNTS as $key) {
            $rules["rows.*.{$key}"] = ['nullable', 'integer', 'min:0', 'max:366'];
        }

        return $rules;
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
