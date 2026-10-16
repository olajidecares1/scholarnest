<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSummary;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manual term attendance: day counts per pupil per term, typed in by a
 * teacher or the School Admin.
 *
 * Everything both portals share lives here, so the Teacher Portal and the
 * School Admin save, check and total the figures by one rule:
 *
 * - The four counts (present, absent, late, excused) add up to the term,
 *   because a day carries exactly one status, as in the daily register.
 * - The total is checked against the term's school days and any difference
 *   is FLAGGED, never refused. A teacher who knows the school shut for a
 *   strike day the calendar does not show must still be able to save.
 * - The daily register and QR check-in are never written to. Manual totals
 *   live in their own table, so turning this on or off changes no register.
 */
class TermAttendance
{
    /**
     * The session and term a page is about: the ones asked for, if valid,
     * otherwise the term today falls in, otherwise the school's current
     * session's most recently started term, otherwise First Term.
     *
     * @return array{0: string, 1: ExamTerm}
     */
    public function resolvePeriod(School $school, ?string $session, ?string $term): array
    {
        $requestedTerm = $term ? ExamTerm::tryFrom($term) : null;
        $sessions = $this->sessionOptions($school);

        if ($session && in_array($session, $sessions, true) && $requestedTerm) {
            return [$session, $requestedTerm];
        }

        if ($current = AcademicTerm::currentFor($school)) {
            return [$current->session, $current->term];
        }

        $currentSession = $school->currentSession();

        $latest = AcademicTerm::where('school_id', $school->id)
            ->where('session', $currentSession)
            ->whereDate('starts_on', '<=', today())
            ->orderByDesc('starts_on')
            ->first();

        return [$currentSession, $latest?->term ?? ExamTerm::First];
    }

    /**
     * @return list<string>
     */
    public function sessionOptions(School $school): array
    {
        return collect(AcademicSession::options())
            ->push($school->currentSession())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function termFor(School $school, string $session, ExamTerm $term): ?AcademicTerm
    {
        return AcademicTerm::rangeFor($school, $session, $term);
    }

    /**
     * How a total compares with the school days, or null when they agree or
     * there is nothing to compare with.
     *
     * @return ?array{kind: 'over'|'under', difference: int, message: string}
     */
    public function discrepancy(int $total, ?int $expected): ?array
    {
        if ($expected === null || $total === $expected) {
            return null;
        }

        $difference = abs($total - $expected);
        $days = $difference === 1 ? 'day' : 'days';

        return $total > $expected
            ? ['kind' => 'over', 'difference' => $difference, 'message' => "{$difference} {$days} more than the {$expected} school days this term"]
            : ['kind' => 'under', 'difference' => $difference, 'message' => "{$difference} {$days} short of the {$expected} school days this term"];
    }

    /**
     * What the daily register already says for each pupil in the term, so a
     * teacher who marked some days by hand, or a school on QR check-in, can
     * start from those figures rather than counting again.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array<int, array<string, int>>
     */
    public function registerTallies(School $school, Collection $studentIds, ?AcademicTerm $term): array
    {
        if (! $term || $studentIds->isEmpty()) {
            return [];
        }

        return AttendanceRecord::where('school_id', $school->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$term->starts_on->toDateString(), $term->ends_on->toDateString()])
            ->get(['student_id', 'status'])
            ->groupBy('student_id')
            ->map(fn (Collection $records) => [
                'days_present' => $records->where('status', AttendanceStatus::Present)->count(),
                'days_absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                'days_late' => $records->where('status', AttendanceStatus::Late)->count(),
                'days_excused' => $records->where('status', AttendanceStatus::Excused)->count(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, AttendanceSummary>
     */
    public function summariesFor(School $school, Collection $studentIds, string $session, ExamTerm $term): Collection
    {
        return AttendanceSummary::where('school_id', $school->id)
            ->where('session', $session)
            ->where('term', $term)
            ->whereIn('student_id', $studentIds)
            ->with(['markedBy:id,name', 'markedByStaff:id,first_name,last_name'])
            ->get()
            ->keyBy('student_id');
    }

    /**
     * Save a class's term totals.
     *
     * A row with every count left blank is "not entered yet" and is skipped,
     * so a teacher can fill in half a class and come back for the rest. A
     * blank count beside filled ones is a zero.
     *
     * When $mayOverwrite is false (a teacher, at a school that keeps
     * corrections for the School Admin) a pupil who already has totals keeps
     * them, unless the figures sent are the same ones, which is what an
     * unchanged row of a re-submitted form looks like.
     *
     * @param  Collection<int, Student>  $students  keyed by id; only these pupils can be written
     * @param  array<int|string, array<string, mixed>>  $rows
     * @return array{saved: int, locked: int, flagged: int}
     */
    public function save(
        School $school,
        Collection $students,
        array $rows,
        string $session,
        ExamTerm $term,
        ?User $admin = null,
        ?Staff $staff = null,
        bool $mayOverwrite = true,
        ?string $className = null,
    ): array {
        $expected = $this->termFor($school, $session, $term)?->schoolDays();
        $existing = $this->summariesFor($school, $students->keys(), $session, $term);
        $result = ['saved' => 0, 'locked' => 0, 'flagged' => 0];

        DB::transaction(function () use ($school, $students, $rows, $session, $term, $admin, $staff, $mayOverwrite, $className, $expected, $existing, &$result) {
            foreach ($rows as $studentId => $row) {
                $student = $students->get((int) $studentId);

                if (! $student || ! is_array($row)) {
                    continue;
                }

                $filled = collect(AttendanceSummary::COUNTS)->contains(fn (string $key) => ($row[$key] ?? null) !== null && $row[$key] !== '');

                if (! $filled) {
                    continue;
                }

                $counts = collect(AttendanceSummary::COUNTS)->mapWithKeys(fn (string $key) => [$key => (int) ($row[$key] ?? 0)])->all();
                $notes = isset($row['notes']) && trim((string) $row['notes']) !== '' ? trim((string) $row['notes']) : null;
                $current = $existing->get($student->id);

                if ($current && ! $mayOverwrite) {
                    if ($current->counts() !== $counts || $current->notes !== $notes) {
                        $result['locked']++;
                    }

                    continue;
                }

                if ($current && $current->counts() === $counts && $current->notes === $notes) {
                    // Nothing changed: leave the row, and who saved it, alone.
                    $result['saved']++;
                } else {
                    AttendanceSummary::updateOrCreate(
                        ['student_id' => $student->id, 'session' => $session, 'term' => $term->value],
                        $counts + [
                            'school_id' => $school->id,
                            'class_name' => $className ?? $student->class_name,
                            'notes' => $notes,
                            'marked_by' => $admin?->id,
                            'marked_by_staff_id' => $staff?->id,
                        ],
                    );
                    $result['saved']++;
                }

                if ($this->discrepancy(array_sum($counts), $expected)) {
                    $result['flagged']++;
                }
            }
        });

        return $result;
    }

    /**
     * The flash message after a save, in the words a teacher reads.
     *
     * @param  array{saved: int, locked: int, flagged: int}  $result
     */
    public function savedMessage(array $result, ExamTerm $term, string $session): string
    {
        $message = "{$term->label()} {$session} attendance saved for {$result['saved']} student(s).";

        if ($result['flagged'] > 0) {
            $message .= " {$result['flagged']} do not match the term's school days and are flagged below.";
        }

        if ($result['locked'] > 0) {
            $message .= " {$result['locked']} already had totals and were not changed: only the School Admin can correct saved totals at this school.";
        }

        return $message;
    }

    /**
     * The attendance a report card prints for one pupil and one term.
     *
     * Manual totals win when the school uses them and this pupil has some for
     * the term; otherwise the daily register is counted exactly as it always
     * was. Manual totals need no term DATES, they are keyed by session and
     * term, so a school that only enters totals still gets attendance on its
     * cards.
     *
     * @return ?array{present: int, absent: int, late: int, excused: int, total: int, percent: ?int, school_days?: ?int, source?: string}
     */
    public function forReportCard(Student $student, string $session, ExamTerm $term, ?AcademicTerm $academicTerm): ?array
    {
        $school = $student->school;

        if ($school?->usesManualAttendance()) {
            $summary = AttendanceSummary::where('school_id', $student->school_id)
                ->where('student_id', $student->id)
                ->where('session', $session)
                ->where('term', $term)
                ->first();

            if ($summary) {
                return [
                    'present' => (int) $summary->days_present,
                    'absent' => (int) $summary->days_absent,
                    'late' => (int) $summary->days_late,
                    'excused' => (int) $summary->days_excused,
                    'total' => $summary->totalDays(),
                    'percent' => $summary->percent(),
                    'school_days' => $academicTerm?->schoolDays(),
                    'source' => 'manual',
                ];
            }
        }

        // No term dates recorded, so there is no window to count the
        // register within. The card says so rather than guessing at one.
        if (! $academicTerm) {
            return null;
        }

        // Filtered by school as well as by student, so one school's register
        // can never reach another school's results, see ReportCardData.
        $records = AttendanceRecord::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->whereBetween('date', [$academicTerm->starts_on->toDateString(), $academicTerm->ends_on->toDateString()])
            ->get();

        return [
            'present' => $records->where('status', AttendanceStatus::Present)->count(),
            'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
            'late' => $records->where('status', AttendanceStatus::Late)->count(),
            'excused' => $records->where('status', AttendanceStatus::Excused)->count(),
            'total' => $records->count(),
            'percent' => $records->isEmpty() ? null : (int) round(($records->filter(fn ($r) => $r->status->isPresentForStats())->count() / $records->count()) * 100),
            'source' => 'register',
        ];
    }

    /**
     * Rows for one class's entry screen: the pupil, any saved totals, what
     * the register says, and how the saved total compares with the term.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(School $school, Collection $students, string $session, ExamTerm $term, ?AcademicTerm $academicTerm): Collection
    {
        $ids = $students->pluck('id');
        $summaries = $this->summariesFor($school, $ids, $session, $term);
        $tallies = $this->registerTallies($school, $ids, $academicTerm);
        $expected = $academicTerm?->schoolDays();

        return $students->map(function (Student $student) use ($summaries, $tallies, $expected) {
            $summary = $summaries->get($student->id);

            return [
                'student' => $student,
                'summary' => $summary,
                'register' => $tallies[$student->id] ?? null,
                'discrepancy' => $summary ? $this->discrepancy($summary->totalDays(), $expected) : null,
            ];
        })->values();
    }

    /**
     * A pupil's saved term totals, newest term first, for their own portal,
     * their guardian's and the API. Empty at a school that does not use
     * manual attendance, so a school that turns it off shows nothing stale.
     *
     * @return Collection<int, AttendanceSummary>
     */
    public function totalsForStudent(Student $student): Collection
    {
        if (! $student->school?->usesManualAttendance()) {
            return collect();
        }

        // 'first' < 'second' < 'third' alphabetically, so the term column
        // sorts in calendar order on its own.
        return $student->attendanceSummaries()
            ->where('school_id', $student->school_id)
            ->orderByDesc('session')
            ->orderByDesc('term')
            ->get();
    }
}
