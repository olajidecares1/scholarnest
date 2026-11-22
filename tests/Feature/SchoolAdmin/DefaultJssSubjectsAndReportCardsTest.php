<?php

/*
| Default subjects for each class (config/subjects.php) and report cards that show only
| the subjects a student has a recorded score in. One test per case in the
| brief's Part 5, plus the edge cases that make each one honest.
*/

use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\DefaultSubjects;
use App\Services\ReportCardData;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create(['school_code' => 'JSD001']);
    activateSchool($this->school);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    DefaultAcademicStructure::seedFor($this->school);
});

function jssDefaultsOfferedIn(School $school, string $className): array
{
    return SubjectOffering::with('subject')
        ->where('school_id', $school->id)
        ->where('class_name', $className)
        ->where('is_default', true)
        ->get()
        ->pluck('subject.name')
        ->sort()
        ->values()
        ->all();
}

function jssDefaultNamesSorted(string $className = 'JSS 1'): array
{
    return collect(array_keys(DefaultSubjects::forClassName(0, $className)))->sort()->values()->all();
}

/**
 * An examination for JSS 1 with the named subjects, each out of 100.
 *
 * @param  list<string>  $subjects
 * @return array<string, ExaminationSubject>
 */
function jssExamWithSubjects(Examination $examination, array $subjects): array
{
    return collect($subjects)->mapWithKeys(fn (string $name) => [
        $name => ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => $name, 'max_score' => 100]),
    ])->all();
}

function jssRecordScore(ExaminationSubject $subject, Student $student, float $score): void
{
    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'test_score' => 0,
        'exam_score' => $score,
        'score' => $score,
    ]);
}

// ---------------------------------------------------------------------------
// Part 1: default subjects
// ---------------------------------------------------------------------------

test('a new school automatically gets the default subjects, marked as defaults', function () {
    foreach (['JSS 1', 'JSS 2', 'JSS 3', 'KG 1', 'SS 1 Science', 'SS 2 Art'] as $class) {
        expect(jssDefaultsOfferedIn($this->school, $class))->toBe(jssDefaultNamesSorted($class));
    }

    expect($this->school->offeredSubjectsFor('JSS 1')->all())
        ->toContain('Intermediate Science', 'Nigerian History', 'Digital Technology')
        ->and($this->school->offeredSubjectsFor('JSS 2')->all())
        ->toContain('BST 1 (PHE & ICT)', 'RNV (Social Studies, CRS & Civic Education)');

    // Not classes without a list.
    expect(SubjectOffering::where('school_id', $this->school->id)->where('class_name', 'Creche')->exists())->toBeFalse();
});

test('the backfill command does not create duplicates, even when run twice', function () {
    // An existing school set up before these lists: its JSS 1 has a couple
    // of the defaults under other spellings, a subject of its own, an old
    // default with no scores, and an old default with a recorded score.
    $old = School::factory()->create();
    $level = $old->academicLevels()->create(['name' => 'JSS', 'sort_order' => 0]);
    $level->classes()->create(['school_id' => $old->id, 'name' => 'JSS 1', 'sort_order' => 0]);

    $phe = Subject::create(['name' => 'Physical and Health Education', 'category' => 'science']);
    $maths = Subject::create(['name' => 'MATHS', 'category' => 'general']);
    $robotics = Subject::create(['name' => 'Robotics', 'category' => 'general']);
    $basicScience = Subject::create(['name' => 'Basic Science', 'category' => 'science']);
    $homeEconomics = Subject::create(['name' => 'Home Economics', 'category' => 'general']);
    foreach ([$phe, $maths, $robotics] as $subject) {
        SubjectOffering::create(['school_id' => $old->id, 'class_name' => 'JSS 1', 'subject_id' => $subject->id]);
    }
    foreach ([$basicScience, $homeEconomics] as $subject) {
        SubjectOffering::create(['school_id' => $old->id, 'class_name' => 'JSS 1', 'subject_id' => $subject->id, 'is_default' => true]);
    }

    $student = Student::factory()->create(['school_id' => $old->id, 'class_name' => 'JSS 1']);
    $examination = Examination::factory()->create(['school_id' => $old->id, 'class_name' => 'JSS 1']);
    jssRecordScore(jssExamWithSubjects($examination, ['home economics'])['home economics'], $student, 55);

    $this->artisan('subjects:backfill')->assertSuccessful();
    $afterFirst = SubjectOffering::where('school_id', $old->id)->where('class_name', 'JSS 1')->count();

    $this->artisan('subjects:backfill-jss')->assertSuccessful();
    $afterSecond = SubjectOffering::where('school_id', $old->id)->where('class_name', 'JSS 1')->count();

    // Every default once, plus Robotics and Home Economics (which has a
    // score). The other spellings count as the default they mean, and
    // Basic Science, no longer a default and never scored, is gone.
    expect($afterFirst)->toBe(count(DefaultSubjects::forClassName(0, 'JSS 1')) + 2)
        ->and($afterSecond)->toBe($afterFirst);

    $names = $old->offeredSubjectsFor('JSS 1');
    expect($names->all())
        ->toContain('Physical and Health Education', 'MATHS', 'Robotics', 'Home Economics')
        ->not->toContain('PHE', 'Mathematics', 'Basic Science');

    // The other spellings are now marked as the defaults they stand for;
    // the school's own subjects are not.
    $isDefault = fn (Subject $subject) => SubjectOffering::where('school_id', $old->id)->where('subject_id', $subject->id)->value('is_default');
    expect($isDefault($phe))->toBeTrue()
        ->and($isDefault($maths))->toBeTrue()
        ->and($isDefault($robotics))->toBeFalse()
        ->and($isDefault($homeEconomics))->toBeFalse();
});

test('the School Admin can add a subject but cannot remove a default subject', function () {
    // The form posted with nothing ticked and a new subject typed in: an
    // attempt to drop every default at once.
    $this->actingAs($this->admin)
        ->post(route('class-subjects.store'), [
            'class_name' => 'JSS 1',
            'subject_ids' => [],
            'custom_subject' => 'Robotics',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', fn ($status) => str_contains($status, "Default subjects can't be removed"));

    expect(jssDefaultsOfferedIn($this->school, 'JSS 1'))->toBe(jssDefaultNamesSorted())
        ->and($this->school->offeredSubjectsFor('JSS 1')->all())->toContain('Robotics');

    // The admin's own subject can be removed again.
    $this->actingAs($this->admin)->post(route('class-subjects.store'), ['class_name' => 'JSS 1', 'subject_ids' => []]);

    expect($this->school->offeredSubjectsFor('JSS 1')->all())->not->toContain('Robotics')
        ->and(jssDefaultsOfferedIn($this->school, 'JSS 1'))->toBe(jssDefaultNamesSorted());
});

test('a subject typed under another spelling of a default is not added twice', function () {
    $this->actingAs($this->admin)->post(route('class-subjects.store'), [
        'class_name' => 'SS 1 Science',
        'subject_ids' => [],
        'custom_subject' => 'physical  and health education',
    ])->assertRedirect();

    expect(Subject::all()->filter(fn ($subject) => DefaultSubjects::defaultNameFor($subject->name) === 'PHE')->count())->toBe(1)
        ->and($this->school->offeredSubjectsFor('SS 1 Science')->all())->toContain('PHE')
        ->and($this->school->offeredSubjectsFor('SS 1 Science')->filter(fn ($name) => $name === 'PHE')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Part 2: everyone sees them, each only their own
// ---------------------------------------------------------------------------

test('a teacher, a student and a parent can see the default subjects', function () {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'admission_number' => 'JSD001/001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'JSD001-PAR-001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);
    $guardian->students()->attach($student->id, ['relationship' => 'Mother']);

    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    TeacherAssignment::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $teacher->id,
        'class_name' => 'JSS 1',
        'type' => TeacherAssignmentType::ClassTeacher,
    ]);

    $defaults = array_keys(DefaultSubjects::forClassName(0, 'JSS 1'));

    // Teacher: the admin opens score entry for JSS 1, and the class teacher
    // finds every default subject ready to mark, in result-sheet order.
    $this->actingAs($this->admin)->post(route('examinations.score-entry.create'), [
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => 'first',
    ])->assertRedirect();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.exams.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder($defaults);

    // Student: web page and API.
    $this->actingAs($student, 'student')
        ->get(route('student.subjects', $this->school))
        ->assertOk()
        ->assertSeeInOrder($defaults);

    app('auth')->forgetGuards();
    $studentToken = $this->postJson('/api/v1/tokens', [
        'role' => 'student', 'school_code' => 'JSD001', 'login' => 'JSD001/001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();

    $api = $this->withToken($studentToken)->getJson('/api/v1/student/subjects')->assertOk();
    expect(collect($api->json('data'))->pluck('name')->all())->toBe($defaults)
        ->and(collect($api->json('data'))->every(fn ($row) => $row['is_default'] === true))->toBeTrue();

    // Parent: web page and API.
    app('auth')->forgetGuards();
    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.children.results', [$this->school, $student]))
        ->assertOk()
        ->assertSeeInOrder($defaults);

    app('auth')->forgetGuards();
    $guardianToken = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian', 'school_code' => 'JSD001', 'login' => 'JSD001-PAR-001',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();

    $api = $this->withToken($guardianToken)->getJson("/api/v1/guardian/children/{$student->uuid}/subjects")->assertOk();
    expect(collect($api->json('data'))->pluck('name')->all())->toBe($defaults);
});

test('a parent cannot read the subjects of a child who is not theirs', function () {
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'JSD001-PAR-002',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);
    $stranger = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian', 'school_code' => 'JSD001', 'login' => 'JSD001-PAR-002',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson("/api/v1/guardian/children/{$stranger->uuid}/subjects")->assertNotFound();
});

test('one school\'s subjects never appear in another school', function () {
    $other = School::factory()->create(['school_code' => 'OTH001']);
    activateSchool($other);
    DefaultAcademicStructure::seedFor($other);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    // The other school adds a subject of its own to its JSS 1.
    $this->actingAs($otherAdmin)->post(route('class-subjects.store'), [
        'class_name' => 'JSS 1',
        'subject_ids' => [],
        'custom_subject' => 'Coding Club',
    ]);

    expect($other->offeredSubjectsFor('JSS 1')->all())->toContain('Coding Club')
        ->and($this->school->offeredSubjectsFor('JSS 1')->all())->not->toContain('Coding Club');

    // And this school's student does not see it, on the web or the API.
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'admission_number' => 'JSD001/009',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    app('auth')->forgetGuards();
    $this->actingAs($student, 'student')
        ->get(route('student.subjects', $this->school))
        ->assertOk()
        ->assertDontSee('Coding Club');

    app('auth')->forgetGuards();
    $token = $this->postJson('/api/v1/tokens', [
        'role' => 'student', 'school_code' => 'JSD001', 'login' => 'JSD001/009',
        'password' => 'Correct-Horse1!', 'device_name' => 'Phone',
    ])->json('token');
    app('auth')->forgetGuards();

    expect(collect($this->withToken($token)->getJson('/api/v1/student/subjects')->json('data'))->pluck('name')->all())
        ->not->toContain('Coding Club');

    // Each school's defaults are its own rows.
    expect(SubjectOffering::where('school_id', $this->school->id)->where('is_default', true)->count())
        ->toBe(SchoolClass::where('school_id', $this->school->id)->with('academicLevel')->get()
            ->sum(fn (SchoolClass $class) => count(DefaultSubjects::forClass($class))));
});

// ---------------------------------------------------------------------------
// Part 3: report cards show only recorded subjects
// ---------------------------------------------------------------------------

test('the report card hides a subject with no recorded score', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Chidi']);
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subjects = jssExamWithSubjects($examination, ['Mathematics', 'Christian Religious Studies', 'Islamic Religious Studies']);

    // A Christian student: CRS recorded, IRS not.
    jssRecordScore($subjects['Mathematics'], $student, 70);
    jssRecordScore($subjects['Christian Religious Studies'], $student, 65);

    $card = ReportCardData::for($examination, $student);

    expect($card['subjects']->pluck('name')->all())->toBe(['Mathematics', 'Christian Religious Studies']);

    // The same on the rendered card (web, print and PDF share this partial)...
    $html = view('school-admin.results._report-card', [...$card, 'canEditTeacherRemark' => false, 'canEditPrincipalRemark' => false])->render();
    expect($html)->toContain('Christian Religious Studies')->not->toContain('Islamic Religious Studies');

    // ...on the School Admin's single report card...
    $this->actingAs($this->admin)
        ->get(route('examinations.report-cards.show', [$examination, $student]))
        ->assertOk()
        ->assertSee('Christian Religious Studies')
        ->assertDontSee('Islamic Religious Studies');

    // ...and in the API the mobile app reads.
    $json = (new \App\Http\Resources\Api\V1\ResultResource($card))->toArray(request());
    expect(collect($json['subjects'])->count())->toBe(2);
});

test('the report card shows a subject where the recorded score is 0', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subjects = jssExamWithSubjects($examination, ['Mathematics', 'French', 'Music']);

    jssRecordScore($subjects['Mathematics'], $student, 80);
    jssRecordScore($subjects['French'], $student, 0);

    $card = ReportCardData::for($examination, $student);

    expect($card['subjects']->pluck('name')->all())->toBe(['Mathematics', 'French'])
        ->and($card['summary']['subjectsGraded'])->toBe(2)
        ->and($card['summary']['average'])->toBe(40.0);
});

test('a score left blank by the teacher is not recorded, but a 0 typed in is', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $blank = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $french = jssExamWithSubjects($examination, ['French'])['French'];

    $this->actingAs($this->admin)->post(route('examinations.scores.store', $french), [
        'test_scores' => [$student->id => '0', $blank->id => ''],
        'exam_scores' => [$student->id => '0', $blank->id => ''],
    ])->assertRedirect();

    expect(ExaminationScore::where('student_id', $student->id)->value('score'))->toEqual(0)
        ->and(ExaminationScore::where('student_id', $blank->id)->exists())->toBeFalse()
        ->and(ReportCardData::for($examination, $student)['subjects']->pluck('name')->all())->toBe(['French'])
        ->and(ReportCardData::for($examination, $blank)['subjects']->all())->toBe([]);
});

test('total, average and position use only the recorded subjects', function () {
    $ada = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Ada']);
    $bola = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Bola']);
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subjects = jssExamWithSubjects($examination, ['Mathematics', 'English Language']);

    // Ada sat Mathematics only; Bola sat both.
    jssRecordScore($subjects['Mathematics'], $ada, 80);
    jssRecordScore($subjects['Mathematics'], $bola, 60);
    jssRecordScore($subjects['English Language'], $bola, 70);

    $card = ReportCardData::for($examination, $ada);

    // Counting English as 0 would make Ada's average 40 and put her second.
    expect($card['summary']['average'])->toBe(80.0)
        ->and($card['summary']['subjectsGraded'])->toBe(1)
        ->and($card['summary']['position'])->toBe(1)
        ->and(ReportCardData::for($examination, $bola)['summary']['position'])->toBe(2);

    // The card's total and maximum are over Mathematics alone.
    expect($card['subjects']->sum(fn ($subject) => (float) $subject->scores->first()->score))->toBe(80.0)
        ->and($card['subjects']->sum('max_score'))->toBe(100);

    $html = view('school-admin.results._report-card', [...$card, 'canEditTeacherRemark' => false, 'canEditPrincipalRemark' => false])->render();
    expect($html)->not->toContain('English Language');
});
