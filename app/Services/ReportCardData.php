<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\Student;
use App\Models\TeacherAssignment;

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
        $subjects = $examination->subjects()->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])->get();
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
            'attendance' => self::attendanceSummaryFor($student, $currentTerm),

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
        ];
    }

    /**
     * One student's attendance for one term, as it appears on their card.
     *
     * Bounded by the term's own dates, so a card for Second Term counts Second
     * Term and nothing else, and filtered by school as well as by student.
     *
     * The school clause is not redundant defensiveness. A student belongs to
     * one school, so filtering by student ALREADY confines this to that
     * school's records, but only for as long as that stays true. Stating it
     * makes "one school's attendance never reaches another school's results"
     * a property of this query rather than a consequence of an invariant kept
     * somewhere else.
     *
     * @return ?array{present: int, absent: int, late: int, excused: int, total: int, percent: ?int}
     */
    private static function attendanceSummaryFor(Student $student, ?AcademicTerm $term): ?array
    {
        // No term dates recorded, so there is no window to count within. The
        // card says so rather than guessing at one, see termDatesMissing.
        if (! $term) {
            return null;
        }

        $records = AttendanceRecord::where('school_id', $student->school_id)
            ->where('student_id', $student->id)
            ->whereBetween('date', [$term->starts_on->toDateString(), $term->ends_on->toDateString()])
            ->get();

        return [
            'present' => $records->where('status', AttendanceStatus::Present)->count(),
            'absent' => $records->where('status', AttendanceStatus::Absent)->count(),
            'late' => $records->where('status', AttendanceStatus::Late)->count(),
            'excused' => $records->where('status', AttendanceStatus::Excused)->count(),
            'total' => $records->count(),
            'percent' => $records->isEmpty() ? null : (int) round(($records->filter(fn ($r) => $r->status->isPresentForStats())->count() / $records->count()) * 100),
        ];
    }
}
