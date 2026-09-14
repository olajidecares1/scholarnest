<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
});

/**
 * A pupil in a class, with one marked examination.
 *
 * @return array{0: Student, 1: Examination}
 */
function pupilWithResult(School $school, string $className = 'JSS 1', ExamTerm $term = ExamTerm::First, int $mark = 72): array
{
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => $className,
        'is_active' => true,
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $className,
        'session' => '2025/2026',
        'term' => $term,
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'Mathematics',
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'test_score' => 30,
        'exam_score' => $mark - 30,
        'score' => $mark,
    ]);

    return [$student, $examination];
}

function issueTokenForRules(School $school, Student $student, Examination $examination, User $admin): string
{
    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

// ---------------------------------------------------------------------------
// 3 & 10. One token, one child, one term
// ---------------------------------------------------------------------------

test('a token for one child does not open another child\'s result', function () {
    [$childA, $examination] = pupilWithResult($this->school);
    $childB = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->guardian->students()->attach([$childA->id, $childB->id]);

    $tokenA = issueTokenForRules($this->school, $childA, $examination, $this->admin);

    // Child A's token, aimed at Child B's result.
    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $childB, $examination]), ['token' => $tokenA])
        ->assertSessionHasErrors('token');

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $childB, $examination]))
        ->assertForbidden();
});

test('a token for one term does not open another term', function () {
    [$student, $firstTerm] = pupilWithResult($this->school, 'JSS 1', ExamTerm::First);

    $secondTerm = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
    ]);

    $this->guardian->students()->attach($student->id);

    $firstTermToken = issueTokenForRules($this->school, $student, $firstTerm, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $student, $secondTerm]), ['token' => $firstTermToken])
        ->assertSessionHasErrors('token');
});

test('a token from another school opens nothing', function () {
    [$student, $examination] = pupilWithResult($this->school);
    $this->guardian->students()->attach($student->id);

    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);
    [$stranger, $theirExamination] = pupilWithResult($otherSchool);

    $foreignToken = issueTokenForRules($otherSchool, $stranger, $theirExamination, $otherAdmin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $student, $examination]), ['token' => $foreignToken])
        ->assertSessionHasErrors('token');
});

test('a wrong token is refused without spending a use', function () {
    [$student, $examination] = pupilWithResult($this->school);
    $this->guardian->students()->attach($student->id);

    issueTokenForRules($this->school, $student, $examination, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $student, $examination]), ['token' => 'NOT-A-REAL-TOKEN'])
        ->assertSessionHasErrors('token');

    expect($this->school->resultCheckingPins()->first()->uses_count)->toBe(0);
});

// ---------------------------------------------------------------------------
// 4. A parent with more than one child
// ---------------------------------------------------------------------------

test('each child is unlocked independently by their own token', function () {
    [$childA, $examinationA] = pupilWithResult($this->school, 'Primary 3');
    [$childB, $examinationB] = pupilWithResult($this->school, 'Primary 5');

    $this->guardian->students()->attach([$childA->id, $childB->id]);

    $tokenA = issueTokenForRules($this->school, $childA, $examinationA, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $childA, $examinationA]), ['token' => $tokenA])
        ->assertSessionHasNoErrors();

    // Child A is open...
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $childA, $examinationA]))
        ->assertOk();

    // ...and Child B is untouched by it.
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $childB, $examinationB]))
        ->assertForbidden();

    $tokenB = issueTokenForRules($this->school, $childB, $examinationB, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $childB, $examinationB]), ['token' => $tokenB])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $childB, $examinationB]))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// 9. An unlock outlives the session
// ---------------------------------------------------------------------------

test('a result already unlocked does not ask for its token again in a new session', function () {
    // The rule that makes this matter: schools issue one token per child per
    // term and do not reissue them. A parent asked again in June would be shut
    // out of a card they were shown in February.
    [$student, $examination] = pupilWithResult($this->school);
    $this->guardian->students()->attach($student->id);

    $token = issueTokenForRules($this->school, $student, $examination, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $student, $examination]), ['token' => $token])
        ->assertSessionHasNoErrors();

    // A new session entirely, signed out, session flushed, signed back in.
    auth('guardian')->logout();
    session()->flush();

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $student, $examination]))
        ->assertOk();
});

test('a term never unlocked stays locked in that same new session', function () {
    [$student, $firstTerm] = pupilWithResult($this->school, 'JSS 1', ExamTerm::First);

    $secondTerm = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
    ]);

    $this->guardian->students()->attach($student->id);

    $token = issueTokenForRules($this->school, $student, $firstTerm, $this->admin);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.results.unlock', [$this->school, $student, $firstTerm]), ['token' => $token]);

    auth('guardian')->logout();
    session()->flush();

    // The one that was opened stays open; the one that never was does not
    // come along with it.
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $student, $firstTerm]))
        ->assertOk();

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $student, $secondTerm]))
        ->assertForbidden();
});

test('a pupil unlocking their own result opens it for their parent too', function () {
    // One token per child per term is what the school issued. Making the pupil
    // and the parent each redeem it separately would need two.
    [$student, $examination] = pupilWithResult($this->school);
    $this->guardian->students()->attach($student->id);

    $token = issueTokenForRules($this->school, $student, $examination, $this->admin);

    $this->actingAs($student, 'student')
        ->post(route('student.results.unlock', [$this->school, $examination]), ['token' => $token])
        ->assertSessionHasNoErrors();

    auth('student')->logout();
    session()->flush();

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results.show', [$this->school, $student, $examination]))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// 1 & 2. The entry point on the profile
// ---------------------------------------------------------------------------

test('a parent finds Check Result on their child\'s profile', function () {
    [$student] = pupilWithResult($this->school);
    $this->guardian->students()->attach($student->id);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.profile', [$this->school, $student]))
        ->assertOk()
        ->assertSee('Check Result')
        ->assertSee(route('guardian.children.results', [$this->school, $student]));
});

test('a pupil finds Check Result on their own profile', function () {
    [$student] = pupilWithResult($this->school);

    $this->actingAs($student, 'student')
        ->get(route('student.profile', $this->school))
        ->assertOk()
        ->assertSee('Check Result')
        ->assertSee(route('student.results.index', $this->school));
});

// ---------------------------------------------------------------------------
// 5, 6 & 7. Linking, from either side, within one school
// ---------------------------------------------------------------------------

test('the School Admin can search this school\'s pupils to link one', function () {
    $ada = Student::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Ada',
        'last_name' => 'Obi',
        'class_name' => 'Primary 3',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('guardians.link-candidates', $this->guardian).'?q=Ada')
        ->assertOk()
        ->assertJsonPath('students.0.name', 'Ada Obi')
        ->assertJsonPath('students.0.class_name', 'Primary 3')
        ->assertJsonCount(1, 'students');

    expect($ada->fresh())->not->toBeNull();
});

test('the pupil search is filtered by class', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'class_name' => 'Primary 3']);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)
        ->getJson(route('guardians.link-candidates', $this->guardian).'?q=Ada&class=Primary+3')
        ->assertOk()
        ->assertJsonCount(1, 'students');
});

test('the pupil search never offers another school\'s child', function () {
    $otherSchool = School::factory()->create();
    Student::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Ada', 'class_name' => 'Primary 3']);

    $this->actingAs($this->admin)
        ->getJson(route('guardians.link-candidates', $this->guardian).'?q=Ada')
        ->assertOk()
        ->assertJsonCount(0, 'students');
});

test('a parent can be linked to several children, and all of them appear', function () {
    $children = collect(['Primary 3', 'Primary 5', 'JSS 1'])->map(fn ($class) => Student::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => $class,
    ]));

    foreach ($children as $child) {
        $this->actingAs($this->admin)
            ->post(route('guardians.children.store', $this->guardian), ['student' => $child->uuid])
            ->assertSessionHasNoErrors();
    }

    expect($this->guardian->students()->count())->toBe(3);

    $response = $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertOk();

    foreach ($children as $child) {
        $response->assertSee($child->fullName());
    }
});

test('the relationship can be made from the child\'s side too', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->actingAs($this->admin)
        ->getJson(route('students.guardian-candidates', $student).'?q='.urlencode($this->guardian->name))
        ->assertOk()
        ->assertJsonPath('guardians.0.uuid', $this->guardian->uuid);

    $this->actingAs($this->admin)
        ->post(route('students.guardians.link', $student), [
            'guardian' => $this->guardian->uuid,
            'relationship' => 'Mother',
        ])
        ->assertSessionHasNoErrors();

    // The same relationship, whichever side it was made from.
    expect($this->guardian->students()->where('students.id', $student->id)->exists())->toBeTrue()
        ->and($student->guardians()->where('guardians.id', $this->guardian->id)->exists())->toBeTrue();
});

test('a parent from another school cannot be linked to this pupil', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $foreignGuardian = Guardian::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->actingAs($this->admin)
        ->post(route('students.guardians.link', $student), ['guardian' => $foreignGuardian->uuid])
        ->assertNotFound();

    expect($student->guardians()->count())->toBe(0);
});

test('the guardian search never offers another school\'s parent', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    Guardian::factory()->create(['school_id' => School::factory()->create()->id, 'name' => 'Stranger Parent']);

    $this->actingAs($this->admin)
        ->getJson(route('students.guardian-candidates', $student).'?q=Stranger')
        ->assertOk()
        ->assertJsonCount(0, 'guardians');
});
