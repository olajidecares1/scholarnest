<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\ResultCheckingPinUsage;
use App\Models\RetiredSchoolResultLink;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;

/**
 * A school's own result-checking address.
 *
 * Two things are required before any result appears: the school's link, which
 * decides WHICH school's tokens are even considered, and a token, which decides
 * which student. Neither is sufficient alone.
 *
 * The tests that matter most are the isolation ones - a link belonging to one
 * school must refuse every other school's tokens, and no amount of editing the
 * address may get around that.
 */
function schoolWithLink(?string $name = null): School
{
    $school = School::factory()->create($name ? ['name' => $name] : []);
    activateSchool($school, PlanKey::Basic);

    return $school->fresh();
}

/**
 * A school with one gradeable result, and a token issued against it.
 *
 * Deliberately local rather than reaching for the equivalents in
 * ResultTokenTest: functions declared in a sibling test file happen to be
 * visible, but relying on that makes this file depend on another one being
 * loaded first.
 *
 * @return array{0: School, 1: Examination, 2: Student}
 */
function linkSchoolWithResult(): array
{
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'term' => 'first',
        'session' => '2026/2027',
    ]);

    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'max_score' => 100,
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 80,
    ]);

    return [$school->fresh(), $examination, $student];
}

function linkIssueTokenFor(School $school, Student $student, Examination $examination): string
{
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

// -----------------------------------------------------------------------------
// One school, one address
// -----------------------------------------------------------------------------

test('every school gets its own result-checking address, and it does not name the school', function () {
    // It used to be built from the name - greenfield-college - and this is the
    // one link a school deliberately spreads, so that name travelled into every
    // message, notice board and referrer header it reached. Random now.
    $school = schoolWithLink('Greenfield College');

    expect($school->result_link_slug)->toHaveLength(16)
        ->not->toContain('greenfield')
        ->and($school->resultLinkUrl())
        ->toEndWith('/'.$school->result_link_slug.'/result')
        ->not->toContain('greenfield');
});

test('two schools with the same name get different addresses', function () {
    $first = schoolWithLink('Greenfield College');
    $second = schoolWithLink('Greenfield College');

    expect($first->result_link_slug)->not->toBe($second->result_link_slug);
});

test('a school result address opens the token prompt', function () {
    $school = schoolWithLink('Greenfield College');

    $this->get($school->resultLinkUrl())
        ->assertOk()
        ->assertSee('School ID / Admission Number');
});

test('an address belonging to no school is not found', function () {
    schoolWithLink('Greenfield College');

    $this->get('/no-such-school/result')->assertNotFound();
});

// -----------------------------------------------------------------------------
// School isolation - the point of the whole feature
// -----------------------------------------------------------------------------

test('a school result link refuses a token belonging to another school', function () {
    [$schoolA, $examinationA, $studentA] = linkSchoolWithResult();
    $schoolB = schoolWithLink('Bright Future School');

    $tokenA = linkIssueTokenFor($schoolA, $studentA, $examinationA);

    // A pupil of School B, named at School B's address, then School A's
    // token. The backend compares the school behind the URL with the school on
    // the token and refuses.
    $studentB = Student::factory()->create(['school_id' => $schoolB->id, 'is_active' => true]);
    identifyForResultCheck($schoolB, $studentB, true);

    $this->post('/'.$schoolB->result_link_slug.'/result', ['code' => $tokenA])
        ->assertSessionHasErrors('code');
});

test('a token only works through its own school address', function () {
    [$school, $examination, $student] = linkSchoolWithResult();
    $token = linkIssueTokenFor($school, $student, $examination);

    identifyForResultCheck($school->fresh(), $student, true);

    $this->post('/'.$school->fresh()->result_link_slug.'/result', ['code' => $token])
        ->assertRedirect();
});

test('editing the address cannot reach a result opened elsewhere', function () {
    [$schoolA, $examinationA, $studentA] = linkSchoolWithResult();
    $schoolB = schoolWithLink('Bright Future School');

    $token = linkIssueTokenFor($schoolA, $studentA, $examinationA);
    identifyForResultCheck($schoolA->fresh(), $studentA, true);
    $this->post('/'.$schoolA->fresh()->result_link_slug.'/result', ['code' => $token]);

    $usage = ResultCheckingPinUsage::latest('id')->first();

    // Swapping the school segment for another school's address does not carry
    // the result across: the usage still belongs where it did.
    $this->get('/'.$schoolB->result_link_slug.'/result/view/'.$usage->uuid)
        ->assertNotFound();
});

test('a refused cross-school token reveals nothing about the student', function () {
    [$schoolA, $examinationA, $studentA] = linkSchoolWithResult();
    $schoolB = schoolWithLink('Bright Future School');

    $token = linkIssueTokenFor($schoolA, $studentA, $examinationA);

    // Named as a School B pupil, then School A's token. School A's child is
    // never mentioned - not their name, not their admission number.
    $studentB = Student::factory()->create(['school_id' => $schoolB->id, 'is_active' => true]);
    identifyForResultCheck($schoolB, $studentB, true);

    $this->from('/'.$schoolB->result_link_slug.'/result')
        ->followingRedirects()
        ->post('/'.$schoolB->result_link_slug.'/result', ['code' => $token])
        ->assertOk()
        ->assertDontSee($studentA->fullName())
        ->assertDontSee($studentA->admission_number)
        ->assertSee('Invalid Result Token');
});

// -----------------------------------------------------------------------------
// The School Admin manages the address
// -----------------------------------------------------------------------------

test('a school admin sees their result link on the token screen', function () {
    $school = schoolWithLink('Greenfield College');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee($school->result_link_slug.'/result');
});

test('a school admin can switch the link off and on again', function () {
    $school = schoolWithLink('Greenfield College');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $link = $school->resultLinkUrl();

    $this->actingAs($admin)->post(route('result-pins.link.toggle'))->assertRedirect();

    // Switched off looks like no link at all, rather than explaining itself.
    $this->get($link)->assertNotFound();

    $this->actingAs($admin)->post(route('result-pins.link.toggle'))->assertRedirect();

    $this->get($link)->assertOk();
});

test('regenerating issues a new address and kills the old one', function () {
    $school = schoolWithLink('Greenfield College');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Captured rather than written out. The slug is random now, so there is
    // no literal to assert against - which is the point of the change.
    $before = $school->result_link_slug;

    $this->actingAs($admin)->post(route('result-pins.link.regenerate'))->assertRedirect();

    $fresh = $school->fresh();

    expect($fresh->result_link_slug)->not->toBe($before);

    $this->get('/'.$before.'/result')->assertNotFound();
    $this->get('/'.$fresh->result_link_slug.'/result')->assertOk();
});

test('a retired address is never handed to another school', function () {
    $school = schoolWithLink('Greenfield College');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $before = $school->result_link_slug;

    $this->actingAs($admin)->post(route('result-pins.link.regenerate'));

    expect(RetiredSchoolResultLink::where('slug', $before)->exists())->toBeTrue();

    // A parent still holding the old link must never be walked to somebody
    // else's school, so the address stays claimed forever.
    $newcomer = schoolWithLink('Greenfield College');

    expect($newcomer->result_link_slug)->not->toBe($before);
});

test('tokens already issued keep working after the link is regenerated', function () {
    [$school, $examination, $student] = linkSchoolWithResult();
    $token = linkIssueTokenFor($school, $student, $examination);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school, PlanKey::Basic);

    $this->actingAs($admin)->post(route('result-pins.link.regenerate'));

    // Tokens are tied to the school, not to the address, so only the URL
    // changed - a school should not have to reissue every token to move link.
    identifyForResultCheck($school->fresh(), $student, true);

    $this->post('/'.$school->fresh()->result_link_slug.'/result', ['code' => $token])
        ->assertRedirect();
});

test('a school admin cannot touch another school link', function () {
    $schoolA = schoolWithLink('Greenfield College');
    $schoolB = schoolWithLink('Bright Future School');

    $adminB = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolB->id]);

    $untouched = $schoolA->result_link_slug;

    // There is no school parameter to tamper with: the action always resolves
    // the acting user's own school.
    $this->actingAs($adminB)->post(route('result-pins.link.regenerate'));

    expect($schoolA->fresh()->result_link_slug)->toBe($untouched);
});

// -----------------------------------------------------------------------------
// The address cannot collide with the platform's own paths
// -----------------------------------------------------------------------------

test('a school cannot claim a reserved address', function () {
    $school = School::factory()->create(['name' => 'Login']);

    expect($school->result_link_slug)->not->toBe('login');
});

test('reserved paths still belong to the application', function () {
    $this->get('/login/result')->assertNotFound();
});
