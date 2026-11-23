<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\TermAttendanceController as StaffTermAttendanceController;
use App\Models\AuditLog;
use App\Services\Attendance\TermAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual term attendance, from the office's side: the setting that turns it
 * on, and the same entry screen the class teacher has, for any class.
 *
 * The School Admin can always correct saved totals; whether a teacher can is
 * the second setting here. Neither setting touches the daily register or QR
 * check-in. On every plan, deliberately outside any plan_feature middleware.
 */
class TermAttendanceController extends Controller
{
    public function __construct(private readonly TermAttendance $attendance) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $classNames = $school->configuredClassNames();
        $requested = $request->string('class')->toString();
        $className = in_array($requested, $classNames, true) ? $requested : ($classNames[0] ?? null);
        [$session, $term] = $this->attendance->resolvePeriod($school, $request->input('session'), $request->input('term'));
        $academicTerm = $this->attendance->termFor($school, $session, $term);

        $students = $className && $school->usesManualAttendance()
            ? $school->students()->where('is_active', true)->where('class_name', $className)->alphabetical()->get()
            : collect();

        return view('school-admin.attendance.term', [
            'school' => $school,
            'classNames' => $classNames,
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

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        abort_unless($school->usesManualAttendance(), 403, 'Turn manual term attendance on before entering totals.');

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($school->configuredClassNames())],
            'session' => ['required', 'string', Rule::in($this->attendance->sessionOptions($school))],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'rows' => ['required', 'array'],
            ...StaffTermAttendanceController::rowRules(),
        ]);

        $term = ExamTerm::from($validated['term']);

        $students = $school->students()
            ->where('is_active', true)
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
            admin: $request->user(),
            className: $validated['class_name'],
        );

        return redirect()
            ->route('attendance.term.index', ['class' => $validated['class_name'], 'session' => $validated['session'], 'term' => $term->value])
            ->with('status', $this->attendance->savedMessage($result, $term, $validated['session']));
    }

    /**
     * One class's term attendance as a CSV, for printing, filing or opening
     * in a spreadsheet. Any class at the School Admin's own school.
     */
    public function export(Request $request): StreamedResponse
    {
        $school = $request->user()->school;

        abort_unless($school->usesManualAttendance(), 403, 'Turn manual term attendance on before exporting totals.');

        $validated = $request->validate([
            'class' => ['required', 'string', Rule::in($school->configuredClassNames())],
            'session' => ['required', 'string', Rule::in($this->attendance->sessionOptions($school))],
            'term' => ['required', Rule::enum(ExamTerm::class)],
        ]);

        return $this->attendance->exportCsv($school, $validated['class'], $validated['session'], ExamTerm::from($validated['term']));
    }

    /**
     * Turn manual term attendance on or off, and say whether teachers may
     * correct saved totals.
     *
     * Nothing is deleted when it is turned off: saved totals stay, report
     * cards go back to the daily register, and turning it on again brings
     * them back exactly as they were.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        // Term Totals are on at every school (School::usesManualAttendance);
        // whether teachers may correct saved totals is the one setting left.
        $request->validate([
            'manual_attendance_teacher_edits' => ['nullable', 'boolean'],
        ]);

        $school->update([
            'manual_attendance_teacher_edits' => $request->boolean('manual_attendance_teacher_edits'),
        ]);

        AuditLog::record(
            'attendance.manual-settings',
            $school->allowsTeacherAttendanceCorrections()
                ? 'Term totals: teachers may correct saved totals.'
                : 'Term totals: only the School Admin corrects saved totals.',
            $school,
        );

        return back()->with('status', $school->allowsTeacherAttendanceCorrections()
            ? 'Saved. Class teachers may correct term totals they have already saved.'
            : 'Saved. Once a class teacher saves a student\'s term totals, only you can change them.');
    }
}
