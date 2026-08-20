<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();
        $className = $this->resolveClass($request, $classes);
        $date = $this->resolveDate($request->input('date'));

        $students = $className
            ? $school->students()->where('is_active', true)->where('class_name', $className)->orderBy('last_name')->get()
            : collect();

        $existing = $className
            ? AttendanceRecord::where('school_id', $school->id)
                ->where('date', $date->toDateString())
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->keyBy('student_id')
            : collect();

        return view('staff.attendance.index', [
            'school' => $school,
            'staff' => $staff,
            'classes' => $classes,
            'className' => $className,
            'students' => $students,
            'date' => $date,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();

        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes)],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array'],
            'records.*' => ['required', Rule::enum(AttendanceStatus::class)],
        ]);

        $className = $validated['class_name'];
        $date = Carbon::parse($validated['date'])->toDateString();

        // Scoped to the teacher's own assigned class - a class teacher
        // cannot mark attendance for students outside their class, even by
        // crafting a request with a different student_id.
        $students = $school->students()->where('class_name', $className)->whereIn('id', array_keys($validated['records']))->get()->keyBy('id');

        $saved = 0;
        foreach ($validated['records'] as $studentId => $status) {
            $student = $students->get($studentId);

            if (! $student) {
                continue;
            }

            AttendanceRecord::updateOrCreate(
                ['student_id' => $student->id, 'date' => $date],
                [
                    'school_id' => $school->id,
                    'class_name' => $className,
                    'status' => $status,
                    'marked_by_staff_id' => $staff->id,
                ],
            );
            $saved++;
        }

        return back()->with('status', "Attendance for {$date} saved for {$saved} student(s).");
    }

    public function history(Request $request, School $school): View
    {
        $staff = $request->user('staff');
        $classes = $staff->classesAsClassTeacher();
        $className = $this->resolveClass($request, $classes);
        $period = in_array($request->string('period')->toString(), ['week', 'month', 'term'], true)
            ? $request->string('period')->toString()
            : 'week';

        [$start, $end, $rangeLabel, $termConfigured] = $this->resolveRange($school, $period);

        $summaries = collect();

        if ($className && $start && $end) {
            $students = $school->students()->where('is_active', true)->where('class_name', $className)->orderBy('last_name')->get();

            $records = AttendanceRecord::where('school_id', $school->id)
                ->whereIn('student_id', $students->pluck('id'))
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->groupBy('student_id');

            $summaries = $students->map(function ($student) use ($records) {
                $studentRecords = $records->get($student->id, collect());

                return [
                    'student' => $student,
                    'present' => $studentRecords->where('status', AttendanceStatus::Present)->count(),
                    'absent' => $studentRecords->where('status', AttendanceStatus::Absent)->count(),
                    'late' => $studentRecords->where('status', AttendanceStatus::Late)->count(),
                    'excused' => $studentRecords->where('status', AttendanceStatus::Excused)->count(),
                    'total' => $studentRecords->count(),
                    'percent' => $studentRecords->isEmpty() ? null : (int) round(($studentRecords->filter(fn ($r) => $r->status->isPresentForStats())->count() / $studentRecords->count()) * 100),
                ];
            });
        }

        return view('staff.attendance.history', [
            'school' => $school,
            'classes' => $classes,
            'className' => $className,
            'period' => $period,
            'rangeLabel' => $rangeLabel,
            'termConfigured' => $termConfigured,
            'summaries' => $summaries,
        ]);
    }

    /**
     * The class to show: the one requested via ?class=, if the teacher is
     * actually the Class Teacher of it, otherwise the first class they're
     * assigned to (or null if they hold none).
     *
     * @param  Collection<int, string>  $classes
     */
    private function resolveClass(Request $request, $classes): ?string
    {
        $requested = $request->string('class')->toString();

        if ($requested && $classes->contains($requested)) {
            return $requested;
        }

        return $classes->first();
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string, 3: bool}
     */
    private function resolveRange(School $school, string $period): array
    {
        if ($period === 'month') {
            return [now()->startOfMonth(), now()->endOfMonth(), now()->format('F Y'), true];
        }

        if ($period === 'term') {
            $terms = AcademicTerm::where('school_id', $school->id)->where('session', AcademicSession::current())->get();

            // Prefer whichever configured term today's date actually falls
            // within; if none matches (e.g. a holiday between terms), fall
            // back to the most recently-ended one rather than showing nothing.
            $term = $terms->first(fn (AcademicTerm $term) => today()->between($term->starts_on, $term->ends_on))
                ?? $terms->sortByDesc('ends_on')->first();

            if (! $term) {
                return [null, null, 'Term dates not set', false];
            }

            return [$term->starts_on, $term->ends_on, "{$term->term->label()} · {$term->session}", true];
        }

        return [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'Last 7 days', true];
    }

    private function resolveDate(?string $date): Carbon
    {
        try {
            $parsed = $date ? Carbon::parse($date) : today();
        } catch (\Exception) {
            $parsed = today();
        }

        return $parsed->greaterThan(today()) ? today() : $parsed;
    }
}
