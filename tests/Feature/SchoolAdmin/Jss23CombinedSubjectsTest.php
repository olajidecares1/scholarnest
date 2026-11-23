<?php

/*
| JSS 2 and JSS 3 (23 November 2026) take BST 1, BST 2, RNV and PVS both as
| one combined subject and as the separate subjects they are made of:
|
|   BST 1 (PHE & ICT)                            PHE, ICT
|   BST 2 (Basic Science & Technology)           Basic Science, Basic Technology
|   RNV (Social Studies, CRS & Civic Education)  Social Studies, CRS, Civic Education
|   PVS (Agric & Home Economics)                 Agricultural Science, Home Economics
|
| A school scores whichever it uses. The other may stay blank without
| holding the result back, and only subjects with a recorded score print
| on the report card. For every school, and for the School Admin, class
| teachers, students and parents alike.
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
use App\Services\CombinedSubjects;
use App\Services\DefaultClassSubjects;
use App\Services\ExaminationResultCalculator;
use App\Services\ReportCardData;
use App\Services\ResultCompleteness;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

const JSS_2_3_LIST = [
    'English Language', 'Mathematics',
    'BST 1 (PHE & ICT)', 'PHE', 'ICT',
    'BST 2 (Basic Science & Technology)', 'Basic Science', 'Basic Technology',
    'Business Studies',
    'RNV (Social Studies, CRS & Civic Education)', 'Social Studies', 'CRS', 'Civic Education',
    'Music', 'French', 'Yoruba',
    'PVS (Agric & Home Economics)', 'Agricultural Science', 'Home Economics',
    'Phonics',
];

// The everyday subjects, scored by every school.
const JSS_2_3_PLAIN = ['English Language', 'Mathematics', 'Business Studies', 'Music', 'French', 'Yoruba', 'Phonics'];
const JSS_2_3_COMBINED = ['BST 1 (PHE & ICT)', 'BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agric & Home Economics)'];
const JSS_2_3_SEPARATE = ['PHE', 'ICT', 'Basic Science', 'Basic Technology', 'Social Studies', 'CRS', 'Civic Education', 'Agricultural Science', 'Home Economics'];

/**
 * This term's JSS 2 examination with the whole JSS 2 & 3 sheet on it, and a
 * pupil scored in exactly $scored.
 *
 * @param  list<string>  $scored
 * @return array{0: Examination, 1: Student}
 */
function jssSheetScoredIn($test, array $scored): array
{
    $examination = Examination::factory()->create([
        'school_id' => $test->school->id, 'class_name' => 'JSS 2', 'session' => '2026/2027', 'term' => ExamTerm::First,
    ]);
    $examination->subjects()->delete();
    $student = Student::factory()->create(['school_id' => $test->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);

    foreach (JSS_2_3_LIST as $name) {
        $paper = $examination->subjects()->create(['name' => $name, 'max_score' => 100]);

        if (in_array($name, $scored, true)) {
            ExaminationScore::factory()->create(['examination_subject_id' => $paper->id, 'student_id' => $student->id, 'test_score' => 30, 'exam_score' => 40, 'score' => 70]);
        }
    }

    return [$examination->fresh(), $student];
}

function jssStatus(Examination $examination, Student $student): string
{
    $summary = ExaminationResultCalculator::summaryForStudent($student, $examination->subjects()->with('scores')->get());

    return ExaminationResultCalculator::statusFor($examination, $summary['subjectsGraded'], $summary['subjectsMissing']);
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $this->school = activateSchool(School::factory()->create(['current_session' => '2026/2027', 'school_code' => 'SEP001']), PlanKey::Standard);
    DefaultAcademicStructure::seedFor($this->school);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

afterEach(fn () => Carbon::setTestNow());

test('JSS 2 and 3 take each combined subject followed by its separate subjects, and only they take the combined ones', function () {
    expect(DefaultClassSubjects::namesForGroup('jss_2_3'))->toBe(JSS_2_3_LIST);

    foreach (array_keys(DefaultClassSubjects::groups()) as $group) {
        if ($group !== 'jss_2_3') {
            expect(DefaultClassSubjects::namesForGroup($group))->not->toContain(...JSS_2_3_COMBINED);
        }
    }

    expect(DefaultClassSubjects::namesForGroup('primary'))->toContain('Social Studies', 'CRS', 'Civic Education', 'Basic Science', 'Basic Technology');
});

test('a new school gets both forms for JSS 2 and JSS 3', function () {
    foreach (['JSS 2', 'JSS 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(JSS_2_3_LIST);
    }
});

test('the combined subjects are recognised however they are written', function (string $written, string $means) {
    expect(DefaultClassSubjects::matchInGroup($written, 'jss_2_3'))->toBe($means);
})->with([
    ['BST 1(PHE & ICT)', 'BST 1 (PHE & ICT)'],
    ['BST 1', 'BST 1 (PHE & ICT)'],
    ['bst 2 (basic science and technology)', 'BST 2 (Basic Science & Technology)'],
    ['RNV ( Social Studies, CRS and Civic Education)', 'RNV (Social Studies, CRS & Civic Education)'],
    ['PVS (Agric and Home economics)', 'PVS (Agric & Home Economics)'],
    ['PVS (Agriculture & Home Economics)', 'PVS (Agric & Home Economics)'],
]);

test('on deploy, a JSS 2 with only the combined subjects gets the separate ones too, never twice, and no score is lost', function () {
    // As it was: the combined subjects only, one under its old spelling,
    // and a BST 1 score on this term's sheet.
    SubjectOffering::where('school_id', $this->school->id)->where('class_name', 'JSS 2')->delete();
    foreach ([...JSS_2_3_PLAIN, 'BST 1 (PHE & ICT)', 'BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agriculture & Home Economics)'] as $name) {
        $subject = Subject::firstOrCreate(['name' => $name], ['category' => 'general']);
        SubjectOffering::create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $subject->id, 'is_default' => true]);
    }
    [$examination, $student] = jssSheetScoredIn($this, ['BST 1 (PHE & ICT)']);

    (require database_path('migrations/2026_11_23_190000_jss_2_3_combined_default_subjects.php'))->up();

    $offered = $this->school->offeredSubjectsFor('JSS 2')->all();
    expect($offered)->toHaveCount(count(JSS_2_3_LIST))
        ->toContain(...JSS_2_3_SEPARATE)
        ->toContain('PVS (Agriculture & Home Economics)')
        ->not->toContain('PVS (Agric & Home Economics)');

    expect((float) $examination->subjects()->where('name', 'BST 1 (PHE & ICT)')->sole()->scores()->value('score'))->toBe(70.0);
});

test('which of a combined subject and its parts may stay blank', function (array $scored, array $missing) {
    expect(array_values(CombinedSubjects::missing(JSS_2_3_LIST, [...JSS_2_3_PLAIN, ...$scored])))->toBe($missing);
})->with([
    'the combined subjects scored' => [JSS_2_3_COMBINED, []],
    'the separate subjects scored' => [JSS_2_3_SEPARATE, []],
    'some of each' => [['BST 1 (PHE & ICT)', 'Basic Science', 'Basic Technology', 'RNV (Social Studies, CRS & Civic Education)', 'Agricultural Science', 'Home Economics'], []],
    'only part of the separate subjects' => [['PHE', 'BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agric & Home Economics)'], ['ICT']],
    'nothing of BST 1 either way' => [['BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agric & Home Economics)'], ['BST 1 (PHE & ICT) (or PHE and ICT)']],
]);

test('a school that scores the combined subjects: complete, publishable, and the card shows only those', function () {
    [$examination, $student] = jssSheetScoredIn($this, [...JSS_2_3_PLAIN, ...JSS_2_3_COMBINED]);

    expect(app(ResultCompleteness::class)->blockers($examination, $student))->toBe([])
        ->and(jssStatus($examination, $student))->toBe('Complete');

    $card = ReportCardData::for($examination, $student)['subjects']->pluck('name');
    expect($card)->toHaveCount(11)->toContain(...JSS_2_3_COMBINED)->not->toContain(...JSS_2_3_SEPARATE);
});

test('a school that scores the separate subjects: complete, publishable, and the card shows only those', function () {
    [$examination, $student] = jssSheetScoredIn($this, [...JSS_2_3_PLAIN, ...JSS_2_3_SEPARATE]);

    expect(app(ResultCompleteness::class)->blockers($examination, $student))->toBe([])
        ->and(jssStatus($examination, $student))->toBe('Complete');

    $card = ReportCardData::for($examination, $student)['subjects']->pluck('name');
    expect($card)->toHaveCount(16)->toContain(...JSS_2_3_SEPARATE)->not->toContain(...JSS_2_3_COMBINED);
});

test('a subject missing either way still holds the result back, saying both ways to complete it', function () {
    [$examination, $student] = jssSheetScoredIn($this, [...JSS_2_3_PLAIN, 'BST 2 (Basic Science & Technology)', 'RNV (Social Studies, CRS & Civic Education)', 'PVS (Agric & Home Economics)']);

    expect(app(ResultCompleteness::class)->blockers($examination, $student))
        ->toBe(['No score has been recorded for BST 1 (PHE & ICT) (or PHE and ICT).'])
        ->and(jssStatus($examination, $student))->toBe('In Progress');
});

test('an everyday subject left blank still holds the result back', function () {
    [$examination, $student] = jssSheetScoredIn($this, ['English Language', 'Mathematics', 'Business Studies', 'Music', 'French', 'Yoruba', ...JSS_2_3_COMBINED]);

    expect(app(ResultCompleteness::class)->blockers($examination, $student))->toBe(['No score has been recorded for Phonics.']);
});

test('the School Admin, the class teacher, the student and the parent all see both forms', function () {
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
        ->assertSeeInOrder(JSS_2_3_LIST);

    // Class teacher: the score page.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_LIST);

    $this->actingAs($this->admin)
        ->get(route('examinations.score-entry', ['class_name' => 'JSS 3', 'session' => '2026/2027', 'term' => 'first']))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_LIST);

    // Student: web page and the mobile app's API.
    app('auth')->forgetGuards();
    $this->actingAs($student, 'student')
        ->get(route('student.subjects', $this->school))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_LIST);

    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'student', 'school_code' => 'SEP001', 'login' => 'SEP001/001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();
    expect(collect($this->withToken($token)->getJson('/api/v1/student/subjects')->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(JSS_2_3_LIST);

    // Parent: web page and API.
    app('auth')->forgetGuards();
    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results', [$this->school, $student]))
        ->assertOk()
        ->assertSeeInOrder(JSS_2_3_LIST);

    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian', 'school_code' => 'SEP001', 'login' => 'SEP001-PAR-001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();
    expect(collect($this->withToken($token)->getJson("/api/v1/guardian/children/{$student->uuid}/subjects")->assertOk()->json('data'))->pluck('name')->all())
        ->toBe(JSS_2_3_LIST);
});
