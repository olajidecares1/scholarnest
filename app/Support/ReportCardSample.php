<?php

namespace App\Support;

use App\Enums\ExamTerm;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A specimen report card, for a School Admin or the AkademicNest Team to look at.
 *
 * Deliberately the SAME array a real card is built from - see
 * ReportCardData::for() - so the preview renders the real template rather than
 * a drawing of it. The ID card learnt this the expensive way: its preview was
 * a hand-built miniature that drifted until what a School Admin approved bore
 * no relation to what printed.
 *
 * Nothing here is saved. Every model is unsaved and every relation is set by
 * hand, which is what lets a school with no examinations yet still see exactly
 * what its report cards will look like.
 */
final class ReportCardSample
{
    /**
     * Ten subjects with marks that produce a believable spread of grades -
     * a card that is all A's tells a School Admin nothing about how a C looks.
     *
     * @var list<array{0: string, 1: int, 2: int}>
     */
    private const SUBJECTS = [
        ['English Language', 32, 52],
        ['Mathematics', 31, 50],
        ['Basic Science', 30, 48],
        ['Social Studies', 28, 45],
        ['Computer Studies', 34, 54],
        ['Civic Education', 29, 46],
        ['Agricultural Science', 27, 42],
        ['French Language', 26, 41],
        ['Physical & Health Edu.', 32, 53],
        ['Religious Studies', 33, 54],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function for(School $school): array
    {
        $student = self::student($school);
        $examination = self::examination($school);

        // The school hangs off the examination because a score resolves its
        // own grade through subject -> examination -> school. Without it the
        // sample renders every grade as an error.
        $examination->setRelation('school', $school);

        $subjects = self::subjects($examination, $student);

        $percentages = $subjects->map(fn (ExaminationSubject $subject) => $subject->scores->first()->percentage());

        return [
            'school' => $school,
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
            'summary' => [
                'student' => $student,
                'subjectsGraded' => $subjects->count(),
                'averageScore' => round($subjects->sum(fn ($s) => (float) $s->scores->first()->score) / max($subjects->count(), 1), 1),
                'average' => round($percentages->avg(), 1),
                'position' => 3,
            ],
            'report' => self::report($student, $examination),
            'classTeacher' => self::classTeacher($school, $examination),
            // Same keys ReportCardData::attendanceSummaryFor() returns,
            // 'percent' included - the card reads it directly.
            'attendance' => [
                'present' => 101,
                'absent' => 6,
                'late' => 3,
                'excused' => 0,
                'total' => 110,
                'percent' => 92,
            ],
            'numberInClass' => 38,
            'nextTermBegins' => Carbon::parse(now()->year.'-09-15'),
        ];
    }

    private static function student(School $school): Student
    {
        $student = new Student([
            'first_name' => 'Chinedu',
            'last_name' => 'Okafor',
            'admission_number' => 'SAMPLE/2024/001',
            'class_name' => 'Primary 6',
            'house' => 'Blue House',
            'gender' => 'male',
            'date_of_birth' => '2013-05-12',
            'guardian_name' => 'Mr. Francis Okafor',
        ]);

        $student->school_id = $school->id;
        $student->id = 0;
        $student->uuid = '00000000-0000-4000-8000-000000000001';

        return $student;
    }

    private static function examination(School $school): Examination
    {
        $examination = new Examination([
            'name' => 'Third Term Examination',
            'class_name' => 'Primary 6',
            'session' => $school->current_session ?: '2024/2025',
            'term' => ExamTerm::Third,
        ]);

        $examination->school_id = $school->id;
        $examination->id = 0;

        return $examination;
    }

    /**
     * @return Collection<int, ExaminationSubject>
     */
    private static function subjects(Examination $examination, Student $student): Collection
    {
        return collect(self::SUBJECTS)->map(function (array $row, int $index) use ($examination, $student) {
            [$name, $test, $exam] = $row;

            $subject = new ExaminationSubject([
                'name' => $name,
                'max_score' => 100,
            ]);

            $subject->examination_id = $examination->id;
            $subject->id = $index + 1;
            $subject->setRelation('examination', $examination);

            $score = new ExaminationScore([
                'test_score' => $test,
                'exam_score' => $exam,
                'score' => $test + $exam,
            ]);

            $score->student_id = $student->id;
            $score->examination_subject_id = $subject->id;
            $score->setRelation('subject', $subject);

            $subject->setRelation('scores', collect([$score]));

            return $subject;
        });
    }

    private static function report(Student $student, Examination $examination): ExaminationReport
    {
        $report = new ExaminationReport([
            'teacher_remark' => 'Chinedu is an intelligent and diligent student. He participates actively in class and shows great interest in learning.',
            'principal_remark' => 'Excellent performance! Keep up the good work. We are proud of your progress and conduct.',
        ]);

        $report->student_id = $student->id;
        $report->examination_id = $examination->id;

        return $report;
    }

    private static function classTeacher(School $school, Examination $examination): TeacherAssignment
    {
        $staff = new Staff([
            'first_name' => 'Blessing',
            'last_name' => 'Nwosu',
            'role' => StaffRole::Teacher,
        ]);

        $staff->school_id = $school->id;
        $staff->id = 0;

        $assignment = new TeacherAssignment([
            'class_name' => $examination->class_name,
            'type' => TeacherAssignmentType::ClassTeacher,
        ]);

        $assignment->school_id = $school->id;
        $assignment->setRelation('staff', $staff);

        return $assignment;
    }
}
