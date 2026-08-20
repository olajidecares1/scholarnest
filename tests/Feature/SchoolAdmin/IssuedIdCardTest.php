<?php

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\IssuedIdCard;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;

function issuedCardSchoolAdmin(PlanKey $planKey = PlanKey::Standard): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin can see the list of issued ID cards', function () {
    $admin = issuedCardSchoolAdmin();
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    IssuedIdCard::factory()->create([
        'school_id' => $admin->school_id,
        'holder_type' => IdCardHolderType::Student,
        'holder_uuid' => $student->uuid,
        'card_number' => 'SCH-STU-000001',
        'issued_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('id-cards.issued.index'))
        ->assertOk()
        ->assertSee('SCH-STU-000001')
        ->assertSee('Amaka Obi');
});

test('a school admin can revoke an issued ID card', function () {
    $admin = issuedCardSchoolAdmin();
    $card = IssuedIdCard::factory()->create([
        'school_id' => $admin->school_id,
        'issued_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('id-cards.issued.revoke', $card))
        ->assertRedirect();

    expect($card->fresh()->status)->toBe(IssuedIdCardStatus::Revoked);
    expect($card->fresh()->revoked_at)->not->toBeNull();
});

test('a school admin cannot revoke another school\'s ID card', function () {
    $admin = issuedCardSchoolAdmin();
    $otherSchool = School::factory()->create();
    $card = IssuedIdCard::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->post(route('id-cards.issued.revoke', $card))
        ->assertForbidden();

    expect($card->fresh()->status)->toBe(IssuedIdCardStatus::Active);
});
