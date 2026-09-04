<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function guardianPortalSchool(PlanKey $planKey = PlanKey::Standard, SubscriptionStatus $status = SubscriptionStatus::Active): School
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return $school;
}

/**
 * Sign in at a school's parent portal.
 */
function guardianLogin(School $school, string $login, string $password = 'password'): TestResponse
{
    return test()->post(
        route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]),
        ['login' => $login, 'password' => $password],
    );
}

test('a parent signs in with their Parent ID', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => 'PAR-0001',
        'phone' => '08031234567',
    ]);

    guardianLogin($school, 'PAR-0001')->assertRedirect(route('guardian.dashboard', $school));

    $this->assertAuthenticatedAs($guardian, 'guardian');
});

test('a parent signs in with their phone number', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => 'PAR-0002',
        'phone' => '08031234567',
    ]);

    guardianLogin($school, '08031234567')->assertRedirect(route('guardian.dashboard', $school));

    $this->assertAuthenticatedAs($guardian, 'guardian');
});

test('the phone number is recognised however it is written', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => 'PAR-0003',
        'phone' => '08031234567',
    ]);

    // The school holds one spelling; the parent types another. They are the
    // same telephone, and a parent should not have to guess the school's
    // formatting to reach their own child's results.
    foreach (['+2348031234567', '0803 123 4567', '+234 803-123-4567', '2348031234567'] as $written) {
        guardianLogin($school, $written)->assertRedirect(route('guardian.dashboard', $school));

        $this->assertAuthenticatedAs($guardian, 'guardian');

        auth('guardian')->logout();
    }
});

test('an email address is refused, even the right one', function () {
    $school = guardianPortalSchool();

    Guardian::factory()->create([
        'school_id' => $school->id,
        'email' => 'parent@example.com',
        'guardian_number' => 'PAR-0004',
    ]);

    // Email is stored and used to contact a parent. It is not how they sign
    // in, and the rule is enforced here rather than merely by the absence of
    // an email box on the form.
    guardianLogin($school, 'parent@example.com')->assertSessionHasErrors('login');

    $this->assertGuest('guardian');
});

test('a wrong password is rejected', function () {
    $school = guardianPortalSchool();

    Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'PAR-0005']);

    guardianLogin($school, 'PAR-0005', 'wrong-password')->assertSessionHasErrors('login');

    $this->assertGuest('guardian');
});

test('a parent cannot reach another school with their own Parent ID', function () {
    $schoolA = guardianPortalSchool();
    $schoolB = guardianPortalSchool();

    $guardianA = Guardian::factory()->create([
        'school_id' => $schoolA->id,
        'guardian_number' => 'PAR-0001',
        'phone' => '08031234567',
    ]);

    // Parent IDs and phone numbers are only unique within a school, so both
    // of these exist twice over. Each must reach its own school and no other.
    $guardianB = Guardian::factory()->create([
        'school_id' => $schoolB->id,
        'guardian_number' => 'PAR-0001',
        'phone' => '08031234567',
    ]);

    guardianLogin($schoolA, 'PAR-0001')->assertRedirect(route('guardian.dashboard', $schoolA));
    $this->assertAuthenticatedAs($guardianA, 'guardian');
    auth('guardian')->logout();

    guardianLogin($schoolB, '08031234567')->assertRedirect(route('guardian.dashboard', $schoolB));
    $this->assertAuthenticatedAs($guardianB, 'guardian');
});

test('a phone number shared by two parents is refused rather than guessed at', function () {
    $school = guardianPortalSchool();

    // Two parents of the same child, one household telephone. "The first row
    // that matches" is the wrong answer when the question is who somebody is.
    Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'PAR-0010', 'phone' => '08031234567']);
    Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'PAR-0011', 'phone' => '0803 123 4567']);

    guardianLogin($school, '08031234567')->assertSessionHasErrors('login');

    $this->assertGuest('guardian');

    // Their Parent ID still identifies each of them exactly.
    guardianLogin($school, 'PAR-0011')->assertRedirect(route('guardian.dashboard', $school));
    $this->assertAuthenticated('guardian');
});

test('failed attempts against a parent at one school do not lock out an identically-numbered parent at another', function () {
    $schoolA = guardianPortalSchool();
    $schoolB = guardianPortalSchool();

    Guardian::factory()->create(['school_id' => $schoolA->id, 'guardian_number' => 'PAR-0001']);
    $guardianB = Guardian::factory()->create(['school_id' => $schoolB->id, 'guardian_number' => 'PAR-0001']);

    for ($i = 0; $i < 5; $i++) {
        guardianLogin($schoolA, 'PAR-0001', 'wrong-password');
    }

    guardianLogin($schoolA, 'PAR-0001')->assertSessionHasErrors('login');

    guardianLogin($schoolB, 'PAR-0001')->assertRedirect(route('guardian.dashboard', $schoolB));

    $this->assertAuthenticatedAs($guardianB, 'guardian');
});

test('reformatting a phone number does not buy five more attempts', function () {
    $school = guardianPortalSchool();

    Guardian::factory()->create(['school_id' => $school->id, 'guardian_number' => 'PAR-0020', 'phone' => '08031234567']);

    // Every spelling of one number counts against the same allowance, or the
    // rate limit is a formality anyone can step around.
    foreach (['08031234567', '+2348031234567', '0803 123 4567', '234 803 123 4567', '+234-803-123-4567'] as $written) {
        guardianLogin($school, $written, 'wrong-password');
    }

    guardianLogin($school, '08031234567')->assertSessionHasErrors('login');

    $this->assertGuest('guardian');
});

test('an inactive guardian cannot access the portal', function () {
    $school = guardianPortalSchool();

    Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => 'PAR-0006',
        'is_active' => false,
    ]);

    guardianLogin($school, 'PAR-0006')->assertSessionHasErrors('login');

    $this->assertGuest('guardian');
});

test('a guardian from a school without a qualifying plan is redirected to the locked page', function () {
    $school = guardianPortalSchool(PlanKey::Basic);
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertRedirect(route('guardian.locked', $school));
});

test('a guardian from a school with no active subscription is redirected to the locked page', function () {
    $school = School::factory()->create();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertRedirect(route('guardian.locked', $school));
});

test('a guardian from a school on the exclusive plan can access the portal', function () {
    $school = guardianPortalSchool(PlanKey::Exclusive);
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);
    $guardian->students()->attach(Student::factory()->create(['school_id' => $school->id])->id);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertStatus(200);
});

test('a guardian can log out', function () {
    $school = guardianPortalSchool();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);

    $this->actingAs($guardian, 'guardian')
        ->post(route('guardian.logout', $school))
        ->assertRedirect(route('guardian.login', ['school' => $school, 'token' => $school->portal_guardian_token]));

    $this->assertGuest('guardian');
});

// ---------------------------------------------------------------------------
// The Parent ID belongs to the system
// ---------------------------------------------------------------------------

test('the school admin cannot change a parent ID', function () {
    $school = guardianPortalSchool();
    $admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
    ]);

    $guardian = Guardian::factory()->create([
        'school_id' => $school->id,
        'guardian_number' => 'PAR-FIXED-1',
        'name' => 'Ada Okafor',
    ]);

    $this->actingAs($admin)->put(route('guardians.update', $guardian), [
        'name' => 'Ada Okafor',
        'email' => 'ada@example.com',
        'phone' => '08039999999',
        // Offered, and must be ignored: it is the credential a parent signs in
        // with, so letting the school edit it would let them lock a parent out
        // or hand one parent another's identifier.
        'guardian_number' => 'PAR-TAMPERED',
    ])->assertRedirect();

    expect($guardian->fresh()->guardian_number)->toBe('PAR-FIXED-1')

        // The editable fields did change, so this is not passing because the
        // whole request was rejected.
        ->and($guardian->fresh()->phone)->toBe('08039999999');
});

test('the login form asks for a phone number or parent ID, not an email', function () {
    $school = guardianPortalSchool();

    $response = $this->get(route('guardian.login', [
        'school' => $school,
        'token' => $school->portal_guardian_token,
    ]))->assertOk();

    $response->assertSee('Phone Number or Parent ID')
        ->assertSee('name="login"', false)
        ->assertDontSee('name="email"', false)
        ->assertDontSee('type="email"', false);
});
