<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;

/**
 * A Basic-plan school with its own result address, a pupil, and a marked result.
 *
 * @return array{0: School, 1: Student, 2: Examination}
 */
function flowSchool(string $name = 'Greenfield College'): array
{
    $school = School::factory()->create(['name' => $name, 'result_link_enabled' => true]);
    activateSchool($school, PlanKey::Basic);
    $school->refresh();

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'session' => '2026/2027',
        'term' => 'first',
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'name' => 'Mathematics',
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'test_score' => 32,
        'exam_score' => 51,
        'score' => 83,
    ]);

    return [$school, $student, $examination];
}

function flowToken(School $school, Student $student, Examination $examination): string
{
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

function flowUrl(School $school, string $suffix = ''): string
{
    return '/'.$school->fresh()->result_link_slug.'/result'.$suffix;
}

// -----------------------------------------------------------------------------
// The sequence itself.
// -----------------------------------------------------------------------------

test('the school link opens step one, asking for the School ID', function () {
    [$school] = flowSchool();

    $this->get(flowUrl($school))
        ->assertOk()
        ->assertSee('School ID / Admission Number')
        ->assertSee('Next')
        ->assertSee($school->name)
        // The token is not asked for yet.
        ->assertDontSee('Exam Token');
});

test('a correct admission number moves to step two showing the pupil', function () {
    [$school, $student] = flowSchool();

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number])
        ->assertRedirect(flowUrl($school, '/confirm'));

    $this->get(flowUrl($school, '/confirm'))
        ->assertOk()
        ->assertSee($student->fullName())
        ->assertSee($student->class_name)
        ->assertSee($student->admission_number)
        ->assertSee('Exam Token')
        ->assertSee('Check Result');
});

test('the admission number is matched regardless of letter case', function () {
    [$school, $student] = flowSchool();

    $this->post(flowUrl($school, '/identify'), [
        'admission_number' => mb_strtolower($student->admission_number),
    ])->assertRedirect(flowUrl($school, '/confirm'));
});

test('a pupil with no photo gets a placeholder, never another face', function () {
    [$school, $student] = flowSchool();
    $student->update(['photo_path' => null, 'first_name' => 'Ada', 'last_name' => 'Obi']);

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number]);

    $this->get(flowUrl($school, '/confirm'))
        ->assertOk()
        ->assertSee('AO')
        ->assertDontSee('<img', false);
});

test('the whole sequence ends at the result, with a download offered', function () {
    [$school, $student, $examination] = flowSchool();
    $token = flowToken($school, $student, $examination);

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number]);

    $this->followingRedirects()
        ->post(flowUrl($school), ['code' => $token])
        ->assertOk()
        ->assertSee($student->fullName())
        ->assertSee($school->name)
        ->assertSee('Download');
});

// -----------------------------------------------------------------------------
// The step cannot be skipped.
// -----------------------------------------------------------------------------

test('a token alone gets nowhere without naming the pupil first', function () {
    // The bypass the two-step flow exists to close: posting the token straight
    // to the verify address, with no identification in the session.
    [$school, $student, $examination] = flowSchool();
    $token = flowToken($school, $student, $examination);

    $this->post(flowUrl($school), ['code' => $token])
        ->assertRedirect(flowUrl($school));

    expect($school->resultCheckingPins()->first()->uses_count)->toBe(0);
});

test('step two cannot be opened by typing its address', function () {
    [$school] = flowSchool();

    $this->get(flowUrl($school, '/confirm'))
        ->assertRedirect(flowUrl($school));
});

test('a wrong admission number names nobody', function () {
    [$school, $student] = flowSchool();

    $this->from(flowUrl($school))
        ->followingRedirects()
        ->post(flowUrl($school, '/identify'), ['admission_number' => 'NOT-A-REAL-NUMBER'])
        ->assertOk()
        ->assertDontSee($student->fullName())
        ->assertSee('could not find that School ID');
});

test('a deactivated pupil is refused in the same words as a wrong number', function () {
    // Told apart by nobody outside: "this child has left" is not a fact to
    // hand to whoever is typing.
    [$school, $student] = flowSchool();
    $student->update(['is_active' => false]);

    $this->from(flowUrl($school))
        ->followingRedirects()
        ->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number])
        ->assertOk()
        ->assertSee('could not find that School ID')
        ->assertDontSee($student->fullName());
});

// -----------------------------------------------------------------------------
// One school's link, one school's pupils.
// -----------------------------------------------------------------------------

test('a pupil cannot be named through another school\'s link', function () {
    [$schoolA, $studentA] = flowSchool();
    [$schoolB] = flowSchool('Bright Future School');

    $this->from(flowUrl($schoolB))
        ->followingRedirects()
        ->post(flowUrl($schoolB, '/identify'), ['admission_number' => $studentA->admission_number])
        ->assertOk()
        ->assertDontSee($studentA->fullName())
        ->assertSee('could not find that School ID');
});

test('an identification made at one school does not carry to another', function () {
    // The session is keyed per school, so naming a pupil at School A leaves
    // School B's link exactly where it was.
    [$schoolA, $studentA] = flowSchool();
    [$schoolB] = flowSchool('Bright Future School');

    $this->post(flowUrl($schoolA, '/identify'), ['admission_number' => $studentA->admission_number]);

    $this->get(flowUrl($schoolB, '/confirm'))
        ->assertRedirect(flowUrl($schoolB));
});

test('another pupil\'s token is refused for the pupil who was named', function () {
    // Both children are at the same school and in the same examination - only
    // the binding on the token tells them apart.
    [$school, $studentA, $examination] = flowSchool();

    $studentB = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    $tokenB = flowToken($school, $studentB, $examination);

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $studentA->admission_number]);

    $this->from(flowUrl($school, '/confirm'))
        ->followingRedirects()
        ->post(flowUrl($school), ['code' => $tokenB])
        ->assertOk()
        ->assertSee('Invalid Result Token');

    // Refused before the use was counted, so the other family's token is intact.
    expect($school->resultCheckingPins()->where('bound_student_id', $studentB->id)->first()->uses_count)->toBe(0);
});

test('naming a different pupil replaces the previous identification', function () {
    [$school, $studentA, $examination] = flowSchool();

    $studentB = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $studentA->admission_number]);
    $this->post(flowUrl($school, '/identify'), ['admission_number' => $studentB->admission_number]);

    $this->get(flowUrl($school, '/confirm'))
        ->assertOk()
        ->assertSee($studentB->fullName())
        ->assertDontSee($studentA->fullName());
});

test('going back to step one forgets who was named', function () {
    // A shared computer must not offer the last family's child to the next.
    [$school, $student] = flowSchool();

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number]);
    $this->get(flowUrl($school))->assertOk();

    $this->get(flowUrl($school, '/confirm'))
        ->assertRedirect(flowUrl($school));
});

test('reaching the result forgets who was named', function () {
    [$school, $student, $examination] = flowSchool();
    $token = flowToken($school, $student, $examination);

    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number]);

    // Followed, because it is opening the result that clears the session.
    $this->followingRedirects()->post(flowUrl($school), ['code' => $token])->assertOk();

    $this->get(flowUrl($school, '/confirm'))
        ->assertRedirect(flowUrl($school));
});

test('a switched-off result link answers nothing at any step', function () {
    [$school, $student] = flowSchool();
    $school->update(['result_link_enabled' => false]);

    $this->get(flowUrl($school))->assertNotFound();
    $this->post(flowUrl($school, '/identify'), ['admission_number' => $student->admission_number])->assertNotFound();
    $this->get(flowUrl($school, '/confirm'))->assertNotFound();
});
