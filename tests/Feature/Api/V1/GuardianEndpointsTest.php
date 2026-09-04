<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create(['school_code' => 'GRN001']);
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create([
        'school_id' => $this->school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    // A parent signs in with their Parent ID or phone number, never their
    // email - the same rule the portal enforces, since the API delegates to
    // the very same login request.
    $this->guardian = Guardian::factory()->create([
        'school_id' => $this->school->id,
        'guardian_number' => 'GRN001-PAR-001',
        'password' => Hash::make('Correct-Horse1!'),
        'must_change_password' => false,
    ]);

    $this->child = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->guardian->students()->attach($this->child);

    // Same school, same class, not this guardian's child. The one that
    // matters: tenancy alone would let this through.
    $this->classmate = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->token = $this->postJson('/api/v1/tokens', [
        'role' => 'guardian',
        'school_code' => 'GRN001',
        'login' => 'GRN001-PAR-001',
        'password' => 'Correct-Horse1!',
        'device_name' => 'Parent phone',
    ])->json('token');

    // A null token here means the sign-in above failed, and every test in this
    // file would then fail with an unhelpful TypeError from withToken()
    // instead of naming the real problem.
    expect($this->token)->not->toBeNull();

    app('auth')->forgetGuards();
});

test('a guardian sees their own children and no others', function () {
    $this->withToken($this->token)->getJson('/api/v1/guardian/children')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->child->uuid);
});

test('a classmate of their child is not found', function () {
    // 404 rather than 403: whether that pupil exists is not this guardian's
    // business either.
    $this->withToken($this->token)
        ->getJson("/api/v1/guardian/children/{$this->classmate->uuid}")
        ->assertNotFound();
});

test('a child at another school is not found', function () {
    $stranger = Student::factory()->create(['school_id' => School::factory()->create()->id]);

    $this->withToken($this->token)
        ->getJson("/api/v1/guardian/children/{$stranger->uuid}")
        ->assertNotFound();
});

test('a guardian can read their own child\'s attendance', function () {
    AttendanceRecord::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'student_id' => $this->child->id,
        'class_name' => 'JSS 1',
    ]);

    $this->withToken($this->token)
        ->getJson("/api/v1/guardian/children/{$this->child->uuid}/attendance")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a guardian cannot read a classmate\'s attendance', function () {
    AttendanceRecord::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->classmate->id,
        'class_name' => 'JSS 1',
    ]);

    $this->withToken($this->token)
        ->getJson("/api/v1/guardian/children/{$this->classmate->uuid}/attendance")
        ->assertNotFound();
});

test('a child\'s own contact details are not handed to the guardian', function () {
    // The record, not the child's phone number and address - the same line the
    // student profile page draws.
    $this->child->update(['phone' => '08011112222', 'address' => '12 Awolowo Road']);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/guardian/children/{$this->child->uuid}")
        ->assertOk();

    expect($response->json('data'))->not->toHaveKey('phone')
        ->and($response->json('data'))->not->toHaveKey('address');
});
