<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\RepositoryResult;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * What the School Admin's dashboard actually shows.
 *
 * The dashboard used to be twenty-five cards linking to the same places as the
 * sidebar, a second navigation menu wearing a dashboard's clothes. It told a
 * head teacher nothing they could act on: not how many pupils they have, not
 * whether today's registers were taken, not whose results are still unmarked.
 *
 * Every figure here is read from the database. None of it is a placeholder,
 * and where a school has no data for something the answer is a real zero
 * rather than an invented number.
 */
class SchoolDashboardMetrics
{
    /**
     * The headline counts.
     *
     * @return array<string, array{value: int|string, caption: string}>
     */
    public function headline(School $school): array
    {
        $students = $school->students()->where('is_active', true)->count();
        $staff = $school->staff()->where('is_active', true)->count();
        $guardians = $school->guardians()->count();

        // Classes with at least one active pupil in them, not classes the
        // school has configured. A school that set up thirty classes and
        // admitted pupils into four has four active classes.
        $activeClasses = $school->students()
            ->where('is_active', true)
            ->whereNotNull('class_name')
            ->distinct()
            ->count('class_name');

        return [
            'students' => ['value' => $students, 'caption' => 'Active pupils on roll'],
            'staff' => ['value' => $staff, 'caption' => 'Teachers and other staff'],
            'guardians' => ['value' => $guardians, 'caption' => 'Parent accounts'],
            'classes' => ['value' => $activeClasses, 'caption' => 'Classes with pupils in them'],
        ];
    }

    /**
     * Today's register, and the week behind it.
     *
     * @return array{marked: int, present: int, absent: int, percent: ?int, classesMarked: int, classesTotal: int, week: list<array{day: string, percent: ?int}>}
     */
    public function attendance(School $school): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $todayRows = AttendanceRecord::query()
            ->where('school_id', $school->id)
            ->whereDate('date', $today)
            ->get(['status', 'class_name']);

        $present = $todayRows->filter(fn ($row) => in_array((string) $row->status?->value, ['present', 'late'], true))->count();
        $marked = $todayRows->count();

        $classesTotal = $school->students()
            ->where('is_active', true)
            ->whereNotNull('class_name')
            ->distinct()
            ->count('class_name');

        return [
            'marked' => $marked,
            'present' => $present,
            'absent' => $marked - $present,
            'percent' => $marked > 0 ? (int) round($present / $marked * 100) : null,
            'classesMarked' => $todayRows->pluck('class_name')->filter()->unique()->count(),
            'classesTotal' => $classesTotal,
            'week' => $this->attendanceWeek($school, $today),
        ];
    }

    /**
     * Where this term's results have got to.
     *
     * @return array{examinations: int, expected: int, entered: int, outstanding: int, published: int}
     */
    public function results(School $school): array
    {
        $session = $school->currentSession();

        $examinations = Examination::query()
            ->where('school_id', $school->id)
            ->where('session', $session)
            ->get(['id', 'class_name']);

        if ($examinations->isEmpty()) {
            return ['examinations' => 0, 'expected' => 0, 'entered' => 0, 'outstanding' => 0, 'published' => 0];
        }

        // How many marks SHOULD exist: every pupil in the class, for every
        // subject on the paper. That is what makes "outstanding" a number a
        // head teacher can chase rather than a vague sense of lateness.
        $subjectCounts = DB::table('examination_subjects')
            ->whereIn('examination_id', $examinations->pluck('id'))
            ->selectRaw('examination_id, COUNT(*) as subjects')
            ->groupBy('examination_id')
            ->pluck('subjects', 'examination_id');

        $pupilsPerClass = $school->students()
            ->where('is_active', true)
            ->selectRaw('class_name, COUNT(*) as pupils')
            ->groupBy('class_name')
            ->pluck('pupils', 'class_name');

        $expected = $examinations->sum(
            fn (Examination $examination) => (int) ($subjectCounts[$examination->id] ?? 0)
                * (int) ($pupilsPerClass[$examination->class_name] ?? 0)
        );

        $entered = DB::table('examination_scores')
            ->join('examination_subjects', 'examination_scores.examination_subject_id', '=', 'examination_subjects.id')
            ->whereIn('examination_subjects.examination_id', $examinations->pluck('id'))
            ->count();

        return [
            'examinations' => $examinations->count(),
            'expected' => $expected,
            'entered' => $entered,
            'outstanding' => max($expected - $entered, 0),
            'published' => RepositoryResult::query()
                ->where('school_id', $school->id)
                ->where('session', $session)
                ->count(),
        ];
    }

    /**
     * Things waiting on the School Admin, in the order they should be dealt
     * with. Only non-zero items are returned, a list of noughts is a list
     * nobody reads.
     *
     * @return list<array{label: string, count: int, url: ?string}>
     */
    public function pending(School $school): array
    {
        $items = [];

        $changeRequests = DB::table('profile_change_requests')
            ->where('school_id', $school->id)
            ->where('status', 'pending')
            ->count();

        if ($changeRequests > 0) {
            $items[] = [
                'label' => 'Profile change '.str('request')->plural($changeRequests),
                'count' => $changeRequests,
                'url' => Route::has('profile-change-requests.index')
                    ? route('profile-change-requests.index')
                    : null,
            ];
        }

        $unpaid = $school->invoices()->with('payments')->get()
            ->filter(fn ($invoice) => $invoice->balance() > 0)
            ->count();

        if ($unpaid > 0) {
            $items[] = [
                'label' => 'Unpaid '.str('invoice')->plural($unpaid),
                'count' => $unpaid,
                'url' => $school->canAccessRoute('finance.index') ? route('finance.index') : null,
            ];
        }

        $withoutLogin = $school->staff()->where('is_active', true)->whereNull('password')->count();

        if ($withoutLogin > 0) {
            $items[] = [
                'label' => 'Staff without portal access',
                'count' => $withoutLogin,
                'url' => route('staff.index'),
            ];
        }

        return $items;
    }

    /**
     * The last few things that happened, from the audit log the school already
     * keeps.
     *
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(School $school, int $limit = 8): Collection
    {
        return AuditLog::query()
            ->forSchool($school)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Attendance for each of the last seven days.
     *
     * @return list<array{day: string, percent: ?int}>
     */
    private function attendanceWeek(School $school, CarbonImmutable $today): array
    {
        $from = $today->subDays(6);

        $rows = AttendanceRecord::query()
            ->where('school_id', $school->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $today)
            ->get(['date', 'status']);

        $byDay = $rows->groupBy(fn ($row) => CarbonImmutable::parse($row->date)->toDateString());

        $week = [];

        for ($day = $from; $day <= $today; $day = $day->addDay()) {
            $rowsForDay = $byDay->get($day->toDateString(), collect());
            $marked = $rowsForDay->count();
            $present = $rowsForDay->filter(fn ($row) => in_array((string) $row->status?->value, ['present', 'late'], true))->count();

            $week[] = [
                'day' => $day->format('D'),
                'percent' => $marked > 0 ? (int) round($present / $marked * 100) : null,
            ];
        }

        return $week;
    }
}
