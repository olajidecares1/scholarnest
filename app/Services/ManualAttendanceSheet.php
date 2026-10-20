<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSummary;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Manual attendance for one class and one term: the rows a teacher or School
 * Admin sees, and saving what they enter.
 *
 * Shared by the Teacher Portal and the School Admin panel so both read and
 * write the figures identically, and the report card reads them through
 * cardSummaryFor() below.
 */
class ManualAttendanceSheet
{
    /**
     * One row per student, with the figures to show in the form.
     *
     * When $prefillFromRegister is set and the term has dates, students with
     * no saved totals are filled from the daily register's counts, so a
     * teacher who has been marking statuses day by day starts from those
     * rather than from zero. Nothing is saved until they submit.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{student: Student, values: array<string, int>, saved: bool, fromRegister: bool, locked: bool, updatedAt: ?Carbon}>
     */
    public static function rows(School $school, Collection $students, string $session, ExamTerm $term, bool $prefillFromRegister = false): Collection
    {
        $summaries = AttendanceSummary::where('school_id', $school->id)
            ->where('session', $session)
            ->where('term', $term)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $registerCounts = $prefillFromRegister
            ? self::registerCounts($school, $students, AcademicTerm::rangeFor($school, $session, $term))
            : collect();

        $locked = self::lockedStudentIds($school, $students->pluck('id'), $session, $term);

        return $students->map(function (Student $student) use ($summaries, $registerCounts, $locked) {
            $summary = $summaries->get($student->id);
            $fromRegister = ! $summary && $registerCounts->has($student->id);

            $values = match (true) {
                (bool) $summary => collect(AttendanceSummary::DAY_FIELDS)->mapWithKeys(fn (string $field) => [$field => $summary->{$field}])->all(),
                $fromRegister => $registerCounts->get($student->id),
                default => array_fill_keys(AttendanceSummary::DAY_FIELDS, 0),
            };

            return [
                'student' => $student,
                'values' => $values,
                'saved' => (bool) $summary,
                'fromRegister' => $fromRegister,
                'locked' => $locked->contains($student->id),
                'updatedAt' => $summary?->updated_at,
            ];
        });
    }

    /**
     * The session and term to show: the requested ones if valid, otherwise
     * the term the school's calendar says is current, otherwise First Term of
     * the current session.
     *
     * @return array{0: string, 1: ExamTerm}
     */
    public static function periodFrom(Request $request, School $school): array
    {
        $session = in_array($request->string('session')->toString(), AcademicSession::options(), true)
            ? $request->string('session')->toString()
            : $school->currentSession();

        $term = ExamTerm::tryFrom($request->string('term')->toString())
            ?? AcademicTerm::currentFor($school)?->term
            ?? ExamTerm::First;

        return [$session, $term];
    }

    /**
     * Validation rules for the entered totals, shared by both portals.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'entries' => ['required', 'array'],
            ...collect(AttendanceSummary::DAY_FIELDS)
                ->mapWithKeys(fn (string $field) => ["entries.*.{$field}" => ['nullable', 'integer', 'min:0', 'max:366']])
                ->all(),
        ];
    }

    /**
     * Save the entered totals.
     *
     * Only students in $students are written, whatever ids the request
     * carries, so a teacher cannot reach outside their class. When
     * $respectLocks is set (the Teacher Portal), students whose card for this
     * term has already been published are skipped: correcting a published
     * figure is the School Admin's decision.
     *
     * @param  Collection<int, Student>  $students  keyed by id
     * @param  array<int|string, array<string, int|string|null>>  $entries
     * @return array{saved: int, locked: int, discrepancies: int}
     */
    public static function save(
        School $school,
        Collection $students,
        string $session,
        ExamTerm $term,
        array $entries,
        ?User $user = null,
        ?Staff $staff = null,
        bool $respectLocks = false,
    ): array {
        $schoolDays = AcademicTerm::rangeFor($school, $session, $term)?->school_days;
        $locked = $respectLocks ? self::lockedStudentIds($school, $students->keys(), $session, $term) : collect();

        $result = ['saved' => 0, 'locked' => 0, 'discrepancies' => 0];

        foreach ($entries as $studentId => $entry) {
            $student = $students->get($studentId);

            if (! $student) {
                continue;
            }

            if ($locked->contains($student->id)) {
                $result['locked']++;

                continue;
            }

            $values = collect(AttendanceSummary::DAY_FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => (int) ($entry[$field] ?? 0)])
                ->all();

            $summary = AttendanceSummary::updateOrCreate(
                ['student_id' => $student->id, 'session' => $session, 'term' => $term],
                [
                    ...$values,
                    'school_id' => $school->id,
                    'class_name' => $student->class_name,
                    'recorded_by' => $user?->id,
                    'recorded_by_staff_id' => $staff?->id,
                ],
            );

            $result['saved']++;

            if ($summary->discrepancyAgainst($schoolDays)) {
                $result['discrepancies']++;
            }
        }

        return $result;
    }

    /**
     * What the report card shows for a student's term when manual attendance
     * is on and totals were entered, or null to fall back to the register.
     *
     * @return ?array{present: int, absent: int, late: int, excused: int, total: int, percent: ?int, school_days: ?int, source: string}
     */
    public static function cardSummaryFor(Student $student, string $session, ExamTerm $term, ?AcademicTerm $academicTerm): ?array
    {
        $school = $student->school;

        if (! $school?->manual_attendance_enabled) {
            return null;
        }

        return AttendanceSummary::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->where('session', $session)
            ->where('term', $term)
            ->first()
            ?->toCardSummary($academicTerm?->school_days);
    }

    /**
     * The status message shown after a save.
     *
     * @param  array{saved: int, locked: int, discrepancies: int}  $result
     */
    public static function statusMessage(array $result, ExamTerm $term, string $session): string
    {
        $message = "Attendance totals for {$term->label()} {$session} saved for {$result['saved']} student(s).";

        if ($result['discrepancies'] > 0) {
            $message .= " {$result['discrepancies']} do not match the term's school days, they are flagged below.";
        }

        if ($result['locked'] > 0) {
            $message .= " {$result['locked']} were skipped because their report card is already published, ask your School Admin to correct those.";
        }

        return $message;
    }

    /**
     * Students whose card for this term has been published to the Result
     * Repository.
     *
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, int>
     */
    public static function lockedStudentIds(School $school, Collection $studentIds, string $session, ExamTerm $term): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return RepositoryResult::where('school_id', $school->id)
            ->where('session', $session)
            ->where('term', $term->value)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');
    }

    /**
     * The daily register's counts per student within the term's dates.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, int>>
     */
    private static function registerCounts(School $school, Collection $students, ?AcademicTerm $academicTerm): Collection
    {
        if (! $academicTerm) {
            return collect();
        }

        return AttendanceRecord::where('school_id', $school->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$academicTerm->starts_on->toDateString(), $academicTerm->ends_on->toDateString()])
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $records) => [
                'days_present' => $records->where('status', AttendanceStatus::Present)->count(),
                'days_absent' => $records->where('status', AttendanceStatus::Absent)->count(),
                'days_late' => $records->where('status', AttendanceStatus::Late)->count(),
                'days_excused' => $records->where('status', AttendanceStatus::Excused)->count(),
            ]);
    }
}
