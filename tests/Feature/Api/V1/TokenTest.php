<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create(['school_code' => 'GRN001']);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'admission_number' => 'GRN001/001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
        'class_name' => 'JSS 1',
    ]);
});

/**
 * The shape every sign-in in this file posts.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tokenPayload(array $overrides = []): array
{
    return [
        'role' => 'student',
        'login' => 'GRN001/001',
        'password' => 'Correct-Horse1!',
        'device_name' => "Ada's phone",
        ...$overrides,
    ];
}

/**
 * Make the next request in this test look at the database again.
 *
 * One container serves a whole test, so a guard that has already resolved a
 * user hands the same instance back next time, which would let a revoked
 * token and a deactivated account both go on working here while failing
 * properly in production, where every request builds its own container.
 */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}

test('correct credentials are answered with a bearer token', function () {
    $this->postJson('/api/v1/tokens', tokenPayload())
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'account' => ['role', 'profile', 'school']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('account.role', 'student')
        ->assertJsonPath('account.profile.admission_number', 'GRN001/001');

    expect($this->student->fresh()->tokens)->toHaveCount(1);
});

test('the token that comes back actually opens the API', function () {
    $token = $this->postJson('/api/v1/tokens', tokenPayload())->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'student');
});

test('the token expires rather than lasting forever', function () {
    $expiry = $this->postJson('/api/v1/tokens', tokenPayload())->json('expires_at');

    expect($expiry)->not->toBeNull()
        ->and(Carbon\Carbon::parse($expiry))->toBeBetween(now()->addDays(59), now()->addDays(61));
});

test('no school code is needed to get a token', function () {
    $payload = tokenPayload();

    expect($payload)->not->toHaveKey('school_code');

    $this->postJson('/api/v1/tokens', $payload)
        ->assertCreated()
        ->assertJsonPath('account.role', 'student');
});

test('details that open accounts at two schools are answered with the choice, then a token', function () {
    $second = School::factory()->create(['name' => 'Second School']);
    $plan = Plan::where('key', PlanKey::Standard)->firstOrFail();
    Subscription::factory()->create(['school_id' => $second->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    $twin = Student::factory()->create([
        'school_id' => $second->id,
        'admission_number' => 'GRN001/001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $choice = $this->postJson('/api/v1/tokens', tokenPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('school')
        ->json('schools');

    expect(collect($choice)->pluck('name')->sort()->values()->all())
        ->toBe(collect([$this->school->name, 'Second School'])->sort()->values()->all());

    $this->postJson('/api/v1/tokens', tokenPayload(['school' => $second->portal_key]))
        ->assertCreated();

    expect($twin->fresh()->tokens)->toHaveCount(1)
        ->and($this->student->fresh()->tokens)->toHaveCount(0);
});

test('a school where the details open no account cannot be chosen', function () {
    $other = School::factory()->create();

    $this->postJson('/api/v1/tokens', tokenPayload(['school' => $other->portal_key]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('login');

    expect($this->student->fresh()->tokens)->toHaveCount(0);
});

test('a wrong password is refused', function () {
    $this->postJson('/api/v1/tokens', tokenPayload(['password' => 'not-the-password']))
        ->assertStatus(422);

    expect($this->student->fresh()->tokens)->toHaveCount(0);
});

test('a wrong school code reads exactly like a wrong password', function () {
    // Same status, same field. A bad school code must not tell somebody that
    // the code they tried was the wrong half of the guess.
    $wrongSchool = $this->postJson('/api/v1/tokens', tokenPayload(['school_code' => 'NOPE99']));
    $wrongPassword = $this->postJson('/api/v1/tokens', tokenPayload(['password' => 'not-the-password']));

    expect($wrongSchool->status())->toBe($wrongPassword->status())
        ->and(array_keys($wrongSchool->json('errors')))->toBe(array_keys($wrongPassword->json('errors')));
});

test('a student cannot sign in with another school code', function () {
    $otherSchool = School::factory()->create(['school_code' => 'OTH002']);

    $this->postJson('/api/v1/tokens', tokenPayload(['school_code' => 'OTH002']))
        ->assertStatus(422);

    expect($otherSchool->fresh())->not->toBeNull();
});

test('an account told to change its password is not given a token', function () {
    // The web portal forces the change before anything else. Handing out a
    // token here would be a way round a rule the browser enforces.
    $this->student->update(['must_change_password' => true]);

    $this->postJson('/api/v1/tokens', tokenPayload())
        ->assertStatus(422)
        ->assertJsonPath('errors.login.0', 'Sign in through your school portal first and set a new password, then sign in here.');

    expect($this->student->fresh()->tokens)->toHaveCount(0);
});

test('a deactivated student is refused a token', function () {
    $this->student->update(['is_active' => false]);

    $this->postJson('/api/v1/tokens', tokenPayload())->assertStatus(422);
});

test('a Basic school\'s pupil is not given a token', function () {
    // Student and guardian portals are the premium tier. If the API handed
    // out tokens regardless, it would be a way round a restriction the browser
    // enforces, which is the one thing a second interface onto the same data
    // must never become.
    $basic = Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic])->toArray());
    $this->school->subscriptions()->update(['plan_id' => $basic->id]);

    $this->postJson('/api/v1/tokens', tokenPayload())
        ->assertStatus(422)
        ->assertJsonPath('errors.login.0', 'This school\'s plan does not include the app. Ask your school about upgrading.');

    expect($this->student->fresh()->tokens)->toHaveCount(0);
});

test('a Basic school\'s teacher is still given a token', function () {
    // The staff portal is on every plan: it is where teachers take attendance
    // and enter marks, not a paid extra.
    $basic = Plan::firstOrCreate(['key' => PlanKey::Basic], Plan::factory()->make(['key' => PlanKey::Basic])->toArray());
    $this->school->subscriptions()->update(['plan_id' => $basic->id]);

    Staff::factory()->create([
        'school_id' => $this->school->id,
        'staff_number' => 'GRN001-STAFF-009',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $this->postJson('/api/v1/tokens', tokenPayload([
        'role' => 'staff',
        'login' => 'GRN001-STAFF-009',
    ]))->assertCreated();
});

test('the School Admin guard is not offered on the API', function () {
    $this->postJson('/api/v1/tokens', tokenPayload(['role' => 'web']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('role');
});

test('a device name is required, so a person can tell their sessions apart', function () {
    $payload = tokenPayload();
    unset($payload['device_name']);

    $this->postJson('/api/v1/tokens', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_name');
});

test('issuing a token is audited under the account, not "System"', function () {
    $this->postJson('/api/v1/tokens', tokenPayload())->assertCreated();

    $entry = AuditLog::where('action', 'api.token_issued')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_name)->toBe(trim($this->student->first_name.' '.$this->student->last_name));
});

test('signing out one device leaves the others signed in', function () {
    $phone = $this->postJson('/api/v1/tokens', tokenPayload())->json('token');
    $tablet = $this->postJson('/api/v1/tokens', tokenPayload(['device_name' => 'Tablet']))->json('token');

    $this->withToken($phone)->deleteJson('/api/v1/tokens/current')->assertOk();

    nextRequest();
    $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();

    nextRequest();
    $this->withToken($tablet)->getJson('/api/v1/me')->assertOk();
});

test('signing out everywhere is one request, for a phone that is gone', function () {
    $phone = $this->postJson('/api/v1/tokens', tokenPayload())->json('token');
    $tablet = $this->postJson('/api/v1/tokens', tokenPayload(['device_name' => 'Tablet']))->json('token');

    $this->withToken($tablet)->deleteJson('/api/v1/tokens')->assertOk()->assertJsonPath('revoked', 2);

    nextRequest();
    $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();

    nextRequest();
    $this->withToken($tablet)->getJson('/api/v1/me')->assertUnauthorized();
});

test('no token at all is refused', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('a browser session does not authenticate the API', function () {
    // Sanctum's default is to try the web session before the bearer token.
    // This API carries no CSRF protection, so a cookie authenticating a
    // request here would be a session-riding surface, and the session user is
    // a School Admin, which none of these endpoints are written for.
    $admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($admin)->getJson('/api/v1/me')->assertUnauthorized();
});

test('a made-up token is refused', function () {
    $this->withToken('1|obviously-not-a-real-token')->getJson('/api/v1/me')->assertUnauthorized();
});

test('a token stops working the moment the account is deactivated', function () {
    // The whole reason the active check runs per request rather than at
    // sign-in: this token was minted while the account was in good standing
    // and would otherwise keep working for weeks.
    $token = $this->postJson('/api/v1/tokens', tokenPayload())->json('token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    $this->student->update(['is_active' => false]);

    nextRequest();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('a token stops working the moment the school is deactivated', function () {
    $token = $this->postJson('/api/v1/tokens', tokenPayload())->json('token');

    $this->school->update(['is_active' => false]);

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('a guardian and a staff member can also sign in', function () {
    // The app signs a parent in the same way the portal does: Parent ID or
    // phone number, not email.
    $guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'GRN001-PAR-001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $staff = Staff::factory()->create([
        'school_id' => $this->school->id,
        'staff_number' => 'GRN001-STAFF-001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $this->postJson('/api/v1/tokens', tokenPayload([
        'role' => 'guardian',
        'login' => 'GRN001-PAR-001',
    ]))->assertCreated()->assertJsonPath('account.role', 'guardian');

    $this->postJson('/api/v1/tokens', tokenPayload([
        'role' => 'staff',
        'login' => 'GRN001-STAFF-001',
    ]))->assertCreated()->assertJsonPath('account.role', 'staff');

    expect($guardian->fresh()->tokens)->toHaveCount(1)
        ->and($staff->fresh()->tokens)->toHaveCount(1);
});
