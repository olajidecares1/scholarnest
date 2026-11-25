<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicTerm;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationSubject;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Services\Attendance\TermAttendance;
use Illuminate\Support\Collection;

class ReportCardData
{
    /**
     * Everything the report card (screen preview, print page, and PDF) needs
     * for one student's examination, the single source of truth shared by
     * every portal's report-card controller, so School Admin, Teacher,
     * Student, and Guardian always render the exact same data.
     *
     * @return array<string, mixed>
     */
    /**
     * Within $callback, whatever is the same for every pupil of one class
     * (the class's positions, its size, its class teacher, the term dates) is
     * worked out once and reused for each card.
     *
     * For a whole class's report cards in one go (ClassReportCards). Worked
     * out per card, the class's positions alone were recalculated for every
     * pupil: forty cards took nearly two minutes, almost all of it that.
     */
    public static function remembering(callable $callback): mixed
    {
        $previous = self::$memo;
        self::$memo = [];

        try {
            return $callback();
        } finally {
            self::$memo = $previous;
        }
    }

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    private static function once(string $key, callable $compute): mixed
    {
        if (self::$memo === null) {
            return $compute();
        }

        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = $compute();
        }

        return self::$memo[$key];
    }

    public static function for(Examination $examination, Student $student): array
    {
        $school = $examination->school;
        $key = 'exam:'.$examination->id.':';
        $subjects = self::recordedSubjects($examination->subjectsInResultSheetOrder(
            $examination->subjects()->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])->get()
        ));
        $summary = self::once($key.'summaries', fn () => ExaminationResultCalculator::summariesFor($examination))->firstWhere('student.id', $student->id)
            ?? ExaminationResultCalculator::summaryForStudent($student, $subjects);
        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $currentTerm = self::once($key.'term', fn () => AcademicTerm::rangeFor($school, $examination->session, $examination->term));

        return [
            'school' => $school,
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
            'summary' => $summary,
            'report' => $report,
            'classTeacher' => self::once($key.'class-teacher', fn () => TeacherAssignment::with('staff')
                ->where('school_id', $school->id)
                ->where('class_name', $examination->class_name)
                ->where('type', TeacherAssignmentType::ClassTeacher)
                ->first()),
            'attendance' => self::attendanceSummaryFor($student, $examination, $currentTerm),

            // The class as it sat this examination: today's class for the
            // current session, the pupils who sat it for a past one. See
            // ExaminationResultCalculator::rosterFor().
            'numberInClass' => self::once($key.'size', fn () => ExaminationResultCalculator::rosterFor($examination)->count()),
            'nextTermBegins' => self::once($key.'next-term', fn () => $currentTerm
                ? AcademicTerm::where('school_id', $school->id)
                    ->where('starts_on', '>', $currentTerm->ends_on)
                    ->orderBy('starts_on')
                    ->first()?->starts_on
                : null),

            // First + Second + Third Term, on the Third Term card, for a
            // school that has turned cumulative results on. Null otherwise,
            // and every template treats null as "print the card exactly as
            // before", so a school that leaves the setting off sees no change.
            'sessionSummary' => self::sessionSummaryFor($examination, $student),
        ];
    }

    /**
     * Only the subjects this student has a recorded score in.
     *
     * A subject with no score entered at all is left off the card entirely,
     * not printed blank, "-" or 0, and so it adds nothing to the total, the
     * maximum, the number of subjects or the average. A score of 0 that a
     * teacher actually entered is a recorded score and stays on the card.
     * Checked per student and per term, since each card is one student's one
     * examination: a student with CRS recorded and IRS not sees only CRS.
     *
     * The subjects passed in must have their scores loaded for this one
     * student, as for() and the admin report card load them.
     *
     * @param  Collection<int, ExaminationSubject>  $subjects
     * @return Collection<int, ExaminationSubject>
     */
    public static function recordedSubjects(Collection $subjects): Collection
    {
        return $subjects
            ->filter(fn (ExaminationSubject $subject) => $subject->scores->contains(fn ($score) => $score->score !== null))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function sessionSummaryFor(Examination $examination, Student $student): ?array
    {
        $school = $examination->school;

        if (! $school->usesCumulativeResults() || $examination->term !== ExamTerm::Third) {
            return null;
        }

        return app(SessionResultCalculator::class)->forStudent($school, $student, $examination->class_name, $examination->session);
    }

    /**
     * One student's attendance for one term, as it appears on their card.
     *
     * Manual term totals, for a school that enters them, otherwise the daily
     * register bounded by the term's own dates. Filtered by school as well as
     * by student, so "one school's attendance never reaches another school's
     * results" is a property of the query and not of an invariant kept
     * somewhere else. See App\Services\Attendance\TermAttendance.
     *
     * @return ?array{present: int, absent: int, late: int, excused: int, total: int, percent: ?int, school_days?: ?int, source?: string}
     */
    private static function attendanceSummaryFor(Student $student, Examination $examination, ?AcademicTerm $term): ?array
    {
        return app(TermAttendance::class)->forReportCard($student, $examination->session, $examination->term, $term);
    }
}
