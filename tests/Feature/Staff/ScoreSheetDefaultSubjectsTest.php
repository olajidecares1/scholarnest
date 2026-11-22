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
