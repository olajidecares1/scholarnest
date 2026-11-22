<?php

/*
| The combined subjects are separate subjects:
|
|   BST 1 (PHE & ICT)                            → PHE, ICT
|   BST 2 (Basic Science & Technology), BST     → Basic Science, Basic Technology
|   RNV (Social Studies, CRS & Civic Education) → Social Studies, CRS, Civic Education
|   PVS (Agriculture & Home Economics)           → Agricultural Science, Home Economics
|
| For every school, and for the School Admin, class teachers, students and
| parents alike.
*/

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\DefaultClassSubjects;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

const COMBINED_SUBJECTS = [
    'BST 1 (PHE & ICT)',
    'BST 2 (Basic Science & Technology)',
    'RNV (Social Studies, CRS & Civic Education)',
    'PVS (Agriculture & Home Economics)',
];

const JSS_2_3_SEPARATE = [
    'English Language', 'Mathematics', 'PHE', 'ICT', 'Basic Science', 'Basic Technology',
    'Business Studies', 'Social Studies', 'CRS', 'Civic Education', 'Music', 'French', 'Yoruba',
    'Agricultural Science', 'Home Economics', 'Phonics',
];

beforeEach(function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $this->school = activateSchool(School::factory()->create(['current_session' => '2026/2027', 'school_code' => 'SEP001']), PlanKey::Standard);
    DefaultAcademicStructure::seedFor($this->school);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

afterEach(fn () => Carbon::setTestNow());

test('no class list has a combined subject any more', function () {
    foreach (array_keys(DefaultClassSubjects::groups()) as $group) {
        expect(DefaultClassSubjects::namesForGroup($group))
            ->not->toContain(...COMBINED_SUBJECTS)
            ->not->toContain('BST', 'RNV', 'PVS');
    }

    expect(DefaultClassSubjects::namesForGroup('jss_2_3'))->toBe(JSS_2_3_SEPARATE)
        ->and(DefaultClassSubjects::namesForGroup('primary'))
        ->toContain('Social Studies', 'CRS', 'Civic Education', 'Basic Science', 'Basic Technology')
        ->and(DefaultClassSubjects::namesForGroup('nursery_2'))
        ->toContain('Basic Science', 'Basic Technology');
});

test('a new school gets the separate subjects for JSS 2, JSS 3, Primary and Nursery 2', function () {
    foreach (['JSS 2', 'JSS 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(JSS_2_3_SEPARATE);
    }

    expect($this->school->offeredSubjectsFor('Primary 3')->all())->not->toContain('RNV', 'BST')
        ->and($this->school->offeredSubjectsFor('Nursery 2')->all())->not->toContain('BST');
});

test('a school that already had the combined subjects gets them separated on deploy, and no score is lost', function () {
    // As it was before: the combined subjects on JSS 2 as defaults, and on
    // this term's examination, with a score already entered in BST 1.
    SubjectOffering::where('school_id', $this->school->id)->where('class_name', 'JSS 2')->delete();
    foreach (['English Language', 'Mathematics', ...COMBINED_SUBJECTS] as $name) {
        $subject = Subject::firstOrCreate(['name' => $name], ['category' => 'general']);
        SubjectOffering::create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $subject->id, 'is_default' => true]);
    }

    $examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 2',
        'session' => '2026/2027',
        'term' => ExamTerm::First,
    ]);
    $examination->subjects()->delete();
    $papers = collect(COMBINED_SUBJECTS)->mapWithKeys(fn ($name) => [$name => $examination->subjects()->create(['name' => $name, 'max_score' => 100])]);

    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $papers['BST 1 (PHE & ICT)']->id,
        'student_id' => $student->id,
        'test_score' => 30,
        'exam_score' => 45,
        'score' => 75,
    ]);

    // What `php artisan migrate --force` runs on deploy, for every school.
    (require database_path('migrations/2026_11_22_120000_split_combined_default_subjects.php'))->up();

    // The class takes the separate subjects, and none of the combined ones.
    expect($this->school->offeredSubjectsFor('JSS 2')->all())->toBe(JSS_2_3_SEPARATE);

    // This term's sheet has the separate subjects. The combined ones with
    // nothing scored are gone; BST 1, which has a score, is kept with it.
    $sheet = $examination->subjects()->pluck('name');
    expect($sheet)->toContain(...JSS_2_3_SEPARATE)
        ->toContain('BST 1 (PHE & ICT)')
        ->not->toContain('BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agriculture & Home Economics)');

    expect((float) $papers['BST 1 (PHE & ICT)']->scores()->value('score'))->toBe(75.0);
});

test('the School Admin, the class teacher, the student and the parent all see the separate subjects', function () {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 3',
        'is_active' => true,
        'admission_number' => 'SEP001/001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'SEP001-PAR-001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);
    $guardian->students()->attach($student->id, ['relationship' => 'Father']);

    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $teacher->id,
        'class_name' => 'JSS 3',
        'type' => TeacherAssignmentType::ClassTeacher,
    ]);

    // School Admin: the class's subjects, and the score page.
    $this->actingAs($this->admin)
        ->get(route('class-subjects.index', ['class' => 'JSS 3']))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_SEPARATE)
        ->assertDontSee('BST 1 (PHE', false)
        ->assertDontSee('RNV (', false);

    // Class teacher: the score page.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_SEPARATE)
        ->assertDontSee('BST 2 (', false)
        ->assertDontSee('PVS (', false);

    $this->actingAs($this->admin)
        ->get(route('examinations.score-entry', ['class_name' => 'JSS 3', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_SEPARATE)
        ->assertDontSee('RNV (', false);

    // Student: web page and the mobile app's API.
    app('auth')->forgetGuards();
    $this->actingAs($student, 'student')
        ->get(route('student.subjects', $this->school))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_SEPARATE);

    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'student', 'school_code' => 'SEP001', 'login' => 'SEP001/001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();
    expect(collect($this->withToken($token)->getJson('/api/v1/student/subjects')->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(JSS_2_3_SEPARATE);

    // Parent: web page and API.
    app('auth')->forgetGuards();
    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results', [$this->school, $student]))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_SEPARATE);

    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian', 'school_code' => 'SEP001', 'login' => 'SEP001-PAR-001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();
    expect(collect($this->withToken($token)->getJson("/api/v1/guardian/children/{$student->uuid}/subjects")->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(JSS_2_3_SEPARATE);
});
