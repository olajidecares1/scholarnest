<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\RepositoryResult;
use App\Models\Signature;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The bridge between a live report card and a published one.
 *
 * Two directions, and they have to agree:
 *
 *   snapshot()  turns what ReportCardData built into plain arrays, to be
 *               written into the repository.
 *
 *   rehydrate() turns those arrays back into the exact shape ReportCardData
 *               returns, so the SAME Blade templates render a published card.
 *
 * That second one is the whole reason this class exists. The obvious
 * alternative - a second template that renders the stored arrays - would mean
 * two report cards: the one the school prints and the one a parent downloads,
 * drifting apart a line at a time until somebody notices they disagree. The
 * project already made that mistake once on the check-result page, which used
 * to hand-roll its own subject list and average.
 *
 * The models rehydrate() returns are UNSAVED. They exist to be read by a
 * template and nothing else; none of them is inserted, and their ids are the
 * ones the snapshot recorded rather than anything the database would issue.
 */
class PublishedResultData
{
    /**
     * Write down a report card.
     *
     * @param  array<string, mixed>  $card  As returned by ReportCardData::for()
     * @return array<string, mixed>
     */
    public function snapshot(array $card): array
    {
        /** @var Student $student */
        $student = $card['student'];
        /** @var Examination $examination */
        $examination = $card['examination'];
        /** @var ExaminationReport $report */
        $report = $card['report'];

        return [
            'student' => [
                'admission_number' => $student->admission_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->fullName(),
                'gender' => $student->gender?->value,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'house' => $student->house,
                'guardian_name' => $student->guardian_name,
                'photo_path' => $student->photo_path,
            ],

            'examination' => [
                'name' => $examination->name,
                'class_name' => $examination->class_name,
                'session' => $examination->session,
                'term' => $examination->term->value,
                'exam_date' => $examination->exam_date?->toDateString(),
            ],

            'subjects' => $this->snapshotSubjects($card['subjects']),

            'summary' => [
                'subjectsGraded' => $card['summary']['subjectsGraded'],
                'averageScore' => $card['summary']['averageScore'],
                'average' => $card['summary']['average'],
                'position' => $card['summary']['position'],
            ],

            'attendance' => $card['attendance'],
            'numberInClass' => $card['numberInClass'],
            'nextTermBegins' => $card['nextTermBegins']?->toDateString(),

            'remarks' => [
                'teacher' => $report->teacher_remark,
                'principal' => $report->principal_remark,
            ],

            'classTeacher' => $card['classTeacher']?->staff
                ? [
                    'name' => trim($card['classTeacher']->staff->first_name.' '.$card['classTeacher']->staff->last_name),
                    'staff_number' => $card['classTeacher']->staff->staff_number,

                    // Snapshotted, unlike the school's crest and motto above.
                    // A signature attests to a particular moment: the teacher
                    // who signed this card signed THIS card, and replacing
                    // their signature later - or leaving the school - must not
                    // rewrite what was already published.
                    'signature_path' => $card['classTeacher']->staff->signature?->path,
                ]
                : null,
        ];
    }

    /**
     * Read a published card back as the shape every result template expects.
     *
     * The school is the LIVE school, not a snapshot of one. A report card
     * carries the school's name, crest, motto, address and grade key, and
     * those belong to the school as it is today - a school that changes its
     * logo has changed its logo, not rewritten last term's marks.
     *
     * @return array<string, mixed>
     */
    public function rehydrate(RepositoryResult $record): array
    {
        $payload = $record->payload;
        $school = $record->school;

        $examination = $this->rehydrateExamination($record, $payload['examination']);
        $student = $this->rehydrateStudent($record, $payload['student']);
        $subjects = $this->rehydrateSubjects($payload['subjects'], $examination);

        $report = new ExaminationReport([
            'school_id' => $record->school_id,
            'student_id' => $record->student_id,
            'teacher_remark' => $payload['remarks']['teacher'] ?? null,
            'principal_remark' => $payload['remarks']['principal'] ?? null,
        ]);
        $report->exists = true;

        return [
            'school' => $school,
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
            'summary' => [
                'student' => $student,
                ...$payload['summary'],
            ],
            'report' => $report,
            'classTeacher' => $this->rehydrateClassTeacher($payload['classTeacher'] ?? null),
            'attendance' => $payload['attendance'] ?? null,
            'numberInClass' => $payload['numberInClass'] ?? 0,
            'nextTermBegins' => isset($payload['nextTermBegins'])
                ? Carbon::parse($payload['nextTermBegins'])
                : null,

            // Only ever read, never written - and the templates that offer an
            // edit box check this.
            'canEditTeacherRemark' => false,
            'canEditPrincipalRemark' => false,
        ];
    }

    /**
     * @param  Collection<int, ExaminationSubject>  $subjects
     * @return list<array<string, mixed>>
     */
    private function snapshotSubjects(Collection $subjects): array
    {
        return $subjects->map(function (ExaminationSubject $subject) {
            $score = $subject->scores->first();

            return [
                'name' => $subject->name,
                'max_score' => $subject->max_score,
                'test_score' => $score?->test_score,
                'exam_score' => $score?->exam_score,
                'score' => $score?->score,

                // The grade is written down rather than recalculated on the
                // way out. A school that redraws its grade bands in March has
                // not changed what a child scored in December, and a published
                // card that silently regraded itself would say otherwise.
                'grade' => $score?->grade(),
                'remark' => $score?->remark,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return Collection<int, ExaminationSubject>
     */
    private function rehydrateSubjects(array $subjects, Examination $examination): Collection
    {
        return collect($subjects)->map(function (array $row, int $index) use ($examination) {
            $subject = new ExaminationSubject([
                'name' => $row['name'],
                'max_score' => $row['max_score'],
            ]);
            $subject->id = $index + 1;
            $subject->exists = true;
            $subject->setRelation('examination', $examination);

            $scores = $row['score'] === null && $row['grade'] === null
                ? collect()
                : collect([$this->rehydrateScore($row, $subject)]);

            $subject->setRelation('scores', $scores);

            return $subject;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rehydrateScore(array $row, ExaminationSubject $subject): ExaminationScore
    {
        $score = new ExaminationScore([
            'test_score' => $row['test_score'],
            'exam_score' => $row['exam_score'],
            'score' => $row['score'],
            'remark' => $row['remark'] ?? null,

            // grade() returns the override before it consults anything else,
            // so writing the recorded grade here is what makes a published
            // card immune to a later change of grade bands - without a second
            // code path, and without touching the live model.
            'grade_override' => $row['grade'],
        ]);

        $score->exists = true;
        $score->setRelation('subject', $subject);

        return $score;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rehydrateExamination(RepositoryResult $record, array $payload): Examination
    {
        $examination = new Examination([
            'school_id' => $record->school_id,
            'name' => $payload['name'],
            'class_name' => $payload['class_name'],
            'session' => $payload['session'],
            'term' => $payload['term'],
            'exam_date' => $payload['exam_date'],
        ]);

        $examination->id = $record->examination_id;
        $examination->exists = true;
        $examination->setRelation('school', $record->school);

        return $examination;
    }

    /**
     * The pupil as the card names them.
     *
     * Name and class are the snapshot's, because a card reissued under a
     * married name or a new class would no longer be the document that was
     * approved. The photo is looked up live - it is an illustration, not a
     * figure, and a school that replaces a blurred photograph should not have
     * to republish a term's results to fix it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function rehydrateStudent(RepositoryResult $record, array $payload): Student
    {
        $student = new Student([
            'school_id' => $record->school_id,
            'admission_number' => $payload['admission_number'],
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'gender' => $payload['gender'],
            'date_of_birth' => $payload['date_of_birth'],
            'class_name' => $record->class_name,
            'house' => $payload['house'] ?? null,
            'guardian_name' => $payload['guardian_name'] ?? null,
            'photo_path' => $record->student?->photo_path ?? $payload['photo_path'] ?? null,
        ]);

        $student->id = $record->student_id;
        $student->exists = true;

        return $student;
    }

    /**
     * The teacher whose name is printed on the card.
     *
     * Real models rather than a stdClass that happens to have the right
     * properties: the templates call $classTeacher->staff->fullName(), and a
     * look-alike object satisfies a reader right up until it reaches a method.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function rehydrateClassTeacher(?array $payload): ?TeacherAssignment
    {
        if ($payload === null) {
            return null;
        }

        $staff = new Staff([
            'first_name' => str($payload['name'])->before(' ')->toString(),
            'last_name' => str($payload['name'])->after(' ')->toString(),
            'staff_number' => $payload['staff_number'] ?? null,
        ]);
        $staff->exists = true;

        // Set as the relation the templates actually read, so a published card
        // prints the signature it was published with. Absent from cards
        // published before signatures existed, which then print an unsigned
        // line - correct, because they were.
        $staff->setRelation(
            'signature',
            ($payload['signature_path'] ?? null)
                ? tap(new Signature(['path' => $payload['signature_path']]), fn (Signature $s) => $s->exists = true)
                : null,
        );

        $assignment = new TeacherAssignment;
        $assignment->exists = true;
        $assignment->setRelation('staff', $staff);

        return $assignment;
    }
}
