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
    public static function for(Examination $examination, Student $student): array
    {
        $school = $examination->school;
        $subjects = self::recordedSubjects($examination->subjectsInResultSheetOrder(
            $examination->subjects()->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])->get()
        ));
        $summary = ExaminationResultCalculator::summariesFor($examination)->firstWhere('student.id', $student->id)
            ?? ExaminationResultCalculator::summaryForStudent($student, $subjects);
        $report = ExaminationReport::firstOrCreateFor($examination, $student);
        $currentTerm = AcademicTerm::rangeFor($school, $examination->session, $examination->term);

        return [
            'school' => $school,
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
            'summary' => $summary,
            'report' => $report,
            'classTeacher' => TeacherAssignment::with('staff')
                ->where('school_id', $school->id)
                ->where('class_name', $examination->class_name)
                ->where('type', TeacherAssignmentType::ClassTeacher)
                ->first(),
            'attendance' => self::attendanceSummaryFor($student, $examination, $currentTerm),

            'numberInClass' => Student::where('school_id', $examination->school_id)
                ->where('class_name', $examination->class_name)
                ->where('is_active', true)
                ->count(),
            'nextTermBegins' => $currentTerm
                ? AcademicTerm::where('school_id', $school->id)
                    ->where('starts_on', '>', $currentTerm->ends_on)
                    ->orderBy('starts_on')
                    ->first()?->starts_on
                : null,

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
