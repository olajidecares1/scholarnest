<?php

/*
| The class's default subjects reach the teacher's Test/Exam Score page
| without the School Admin setting anything up.
|
| The defect: subjects were set up for the class, but a class teacher's
| score page listed none of them. Score sheets are built from an
| examination's subjects, and an examination started empty, so the class's
| subjects reached it only if an administrator added each one by hand.
*/

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\DefaultClassSubjects;
use App\Services\ExaminationSubjectSync;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Mid-November: the first term of the 2026/2027 session.
    Carbon::setTestNow('2026-11-20 10:00:00');

    $this->school = activateSchool(School::factory()->create(['current_session' => '2026/2027']), PlanKey::Standard);
    DefaultAcademicStructure::seedFor($this->school);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
});

afterEach(fn () => Carbon::setTestNow());

function assignClassTeacher(Staff $teacher, string $className): void
{
    TeacherAssignment::factory()->create([
        'school_id' => $teacher->school_id,
        'staff_id' => $teacher->id,
        'class_name' => $className,
        'type' => TeacherAssignmentType::ClassTeacher,
    ]);
}

test('a class teacher sees every default subject of their class, with nothing set up by the admin', function (string $className, string $group) {
    assignClassTeacher($this->teacher, $className);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(DefaultClassSubjects::namesForGroup($group), false)
        ->assertDontSee('No subjects are set up for your class yet');

    // This term's examination was created for the class, with its subjects.
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', $className)->sole();
    expect($examination->session)->toBe('2026/2027')
        ->and($examination->term)->toBe(ExamTerm::First)
        ->and($examination->subjects()->count())->toBe(count(DefaultClassSubjects::namesForGroup($group)));
})->with([
    ['KG 1', 'reception'],
    ['Nursery 1', 'nursery_1'],
    ['Nursery 2', 'nursery_2'],
    ['Primary 4', 'primary'],
    ['JSS 1', 'jss_1'],
    ['JSS 3', 'jss_2_3'],
    ['SS 2 Art', 'sss_art'],
]);

test('opening the page again creates nothing twice', function () {
    assignClassTeacher($this->teacher, 'Primary 1');

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();
    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();

    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'Primary 1')->sole();
    expect($examination->subjects()->count())->toBe(count(DefaultClassSubjects::namesForGroup('primary')));
});

test('an examination the admin created empty is filled with the class subjects', function () {
    assignClassTeacher($this->teacher, 'Nursery 2');

    // Made before this fix, with no subjects on it.
    $examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'Nursery 2',
        'session' => '2026/2027',
        'term' => ExamTerm::First,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(DefaultClassSubjects::namesForGroup('nursery_2'), false);

    expect($examination->subjects()->count())->toBe(count(DefaultClassSubjects::namesForGroup('nursery_2')))
        ->and(Examination::where('class_name', 'Nursery 2')->count())->toBe(1);
});

test('the admin\'s new examination starts with the class subjects', function () {
    $this->actingAs($this->admin)->post(route('examinations.store'), [
        'name' => 'First Term Examination',
        'class_name' => 'JSS 2',
        'session' => '2026/2027',
        'term' => 'first',
        'exam_date' => '2026-12-01',
    ])->assertRedirect();

    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'JSS 2')->sole();

    expect($examination->subjectsInResultSheetOrder($examination->subjects()->get())->pluck('name')->all())
        ->toBe(DefaultClassSubjects::namesForGroup('jss_2_3'));
});

test('a subject teacher assigned "Maths" sees Mathematics on the sheet', function () {
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'Primary 5',
        'subject' => 'maths',
        'type' => TeacherAssignmentType::SubjectTeacher,
    ]);

    $html = $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Mathematics')
        // Only their own subject, not the whole class.
        ->not->toContain('Verbal Reasoning');
});

test('an old default the class no longer takes comes off this session\'s sheet, unless it has scores', function () {
    $examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 2',
        'session' => '2026/2027',
        'term' => ExamTerm::First,
    ]);
    $unscored = $examination->subjects()->create(['name' => 'Security Education', 'max_score' => 100]);
    $scored = $examination->subjects()->create(['name' => 'Basic Science', 'max_score' => 100]);
    $own = $examination->subjects()->create(['name' => 'Robotics', 'max_score' => 100]);

    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2']);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $scored->id,
        'student_id' => $student->id,
        'test_score' => 20,
        'exam_score' => 40,
        'score' => 60,
    ]);

    ExaminationSubjectSync::sync($examination);

    $names = $examination->subjects()->pluck('name');

    expect($names)->not->toContain('Security Education')
        // A score was entered: kept.
        ->toContain('Basic Science')
        // Added by hand for this paper and not a platform subject: kept.
        ->toContain('Robotics')
        ->toContain(...DefaultClassSubjects::namesForGroup('jss_2_3'));

    expect($unscored->fresh())->toBeNull();
});

test('a past session\'s examination is left as it was', function () {
    assignClassTeacher($this->teacher, 'Primary 2');

    $old = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'Primary 2',
        'session' => '2025/2026',
        'term' => ExamTerm::Third,
    ]);
    $old->subjects()->create(['name' => 'Social Studies', 'max_score' => 100]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();

    expect($old->subjects()->pluck('name')->all())->toBe(['Social Studies']);
});

test('a teacher of another school\'s class gets nothing created', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Standard);
    DefaultAcademicStructure::seedFor($other);

    assignClassTeacher($this->teacher, 'Primary 3');

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();

    expect(Examination::where('school_id', $other->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// The whole-class sheet: every subject, a Test and an Exam box per student
// ---------------------------------------------------------------------------

test('picking a class shows every subject with a Test and an Exam box for each student', function () {
    assignClassTeacher($this->teacher, 'Primary 4');
    $students = Student::factory()->count(2)->create(['school_id' => $this->school->id, 'class_name' => 'Primary 4', 'is_active' => true]);

    $html = $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', [$this->school, 'class_name' => 'Primary 4']))
        ->assertOk()
        ->assertSee('Save all scores')
        ->assertSeeInOrder(DefaultClassSubjects::namesForGroup('primary'), false)
        ->getContent();

    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'Primary 4')->sole();

    foreach ($examination->subjects as $subject) {
        foreach ($students as $student) {
            expect($html)->toContain('name="scores['.$subject->id.']['.$student->id.'][test]"')
                ->toContain('name="scores['.$subject->id.']['.$student->id.'][exam]"');
        }
    }
});

test('a class teacher saves Test and Exam scores for several subjects at once', function () {
    assignClassTeacher($this->teacher, 'JSS 1');
    $ada = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $bola = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'JSS 1')->sole();
    $maths = $examination->subjects()->where('name', 'Mathematics')->sole();
    $french = $examination->subjects()->where('name', 'French')->sole();

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.class-scores.update', [$this->school, $examination]), [
            'scores' => [
                $maths->id => [
                    $ada->id => ['test' => '35', 'exam' => '50'],
                    $bola->id => ['test' => '', 'exam' => ''],
                ],
                $french->id => [
                    $ada->id => ['test' => '20', 'exam' => ''],
                    $bola->id => ['test' => '0', 'exam' => '0'],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn ($status) => str_contains($status, 'Saved 3 score(s) across 2 subject(s)'));

    expect((float) ExaminationScore::where('examination_subject_id', $maths->id)->where('student_id', $ada->id)->value('score'))->toBe(85.0)
        ->and(ExaminationScore::where('examination_subject_id', $maths->id)->where('student_id', $bola->id)->exists())->toBeFalse()
        ->and((float) ExaminationScore::where('examination_subject_id', $french->id)->where('student_id', $ada->id)->value('score'))->toBe(20.0)
        ->and((float) ExaminationScore::where('examination_subject_id', $french->id)->where('student_id', $bola->id)->value('score'))->toBe(0.0);

    // The saved scores are in the boxes when the sheet opens again.
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.index', [$this->school, 'class_name' => 'JSS 1']))
        ->assertOk()
        ->assertSee('Saved 3 score(s)');
});

test('a full class sheet sent as one field is saved in full', function () {
    // 12 subjects x 45 students x 2 boxes = 1,080 boxes: more than PHP
    // accepts as separate form fields by default.
    assignClassTeacher($this->teacher, 'Nursery 2');
    $students = Student::factory()->count(45)->create(['school_id' => $this->school->id, 'class_name' => 'Nursery 2', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'Nursery 2')->sole();

    $scores = [];
    foreach ($examination->subjects as $subject) {
        foreach ($students as $student) {
            $scores[$subject->id][$student->id] = ['test' => 30, 'exam' => 45];
        }
    }

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.class-scores.update', [$this->school, $examination]), ['scores_json' => json_encode($scores)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ExaminationScore::whereIn('examination_subject_id', $examination->subjects->pluck('id'))->count())
        ->toBe(count(DefaultClassSubjects::namesForGroup('nursery_2')) * 45);
});

test('a score over the maximum is refused and nothing is saved', function () {
    assignClassTeacher($this->teacher, 'Primary 1');
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Primary 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school));
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'Primary 1')->sole();
    [$first, $second] = $examination->subjects()->take(2)->get()->all();

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.class-scores.update', [$this->school, $examination]), [
            'scores' => [
                $first->id => [$student->id => ['test' => '30', 'exam' => '40']],
                $second->id => [$student->id => ['test' => '41', 'exam' => '10']],
            ],
        ])
        ->assertSessionHasErrors();

    expect(ExaminationScore::where('student_id', $student->id)->exists())->toBeFalse();
});

test('a subject teacher cannot save scores for a subject they do not teach', function () {
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'class_name' => 'JSS 1',
        'subject' => 'Mathematics',
        'type' => TeacherAssignmentType::SubjectTeacher,
    ]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school))->assertOk();
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'JSS 1')->sole();
    $maths = $examination->subjects()->where('name', 'Mathematics')->sole();
    $french = $examination->subjects()->where('name', 'French')->sole();

    $this->actingAs($this->teacher, 'staff')->put(route('staff.exams.class-scores.update', [$this->school, $examination]), [
        'scores' => [
            $maths->id => [$student->id => ['test' => '30', 'exam' => '40']],
            $french->id => [$student->id => ['test' => '30', 'exam' => '40']],
        ],
    ])->assertRedirect();

    expect(ExaminationScore::where('examination_subject_id', $maths->id)->exists())->toBeTrue()
        ->and(ExaminationScore::where('examination_subject_id', $french->id)->exists())->toBeFalse();
});

test('a student from another class cannot be given a score through the sheet', function () {
    assignClassTeacher($this->teacher, 'Primary 2');
    $outsider = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Primary 3', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')->get(route('staff.exams.index', $this->school));
    $examination = Examination::where('school_id', $this->school->id)->where('class_name', 'Primary 2')->sole();
    $subject = $examination->subjects()->first();

    $this->actingAs($this->teacher, 'staff')->put(route('staff.exams.class-scores.update', [$this->school, $examination]), [
        'scores' => [$subject->id => [$outsider->id => ['test' => '30', 'exam' => '40']]],
    ])->assertRedirect();

    expect(ExaminationScore::where('student_id', $outsider->id)->exists())->toBeFalse();
});

test('another school\'s examination cannot be saved through this school\'s portal', function () {
    assignClassTeacher($this->teacher, 'Primary 2');

    $other = activateSchool(School::factory()->create(), PlanKey::Standard);
    $foreign = Examination::factory()->create(['school_id' => $other->id, 'class_name' => 'Primary 2']);
    $subject = $foreign->subjects()->create(['name' => 'Mathematics', 'max_score' => 100]);

    $this->actingAs($this->teacher, 'staff')
        ->put(route('staff.exams.class-scores.update', [$this->school, $foreign]), [
            'scores' => [$subject->id => []],
        ])
        ->assertNotFound();

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.exams.scores.edit', [$this->school, $foreign, $subject]))
        ->assertNotFound();
});
