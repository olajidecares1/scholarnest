<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;

function changeRequestSchoolAdmin(): User
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin can see the list of profile change requests', function () {
    $admin = changeRequestSchoolAdmin();
    $student = Student::factory()->create(['school_id' => $admin->school_id]);
    ProfileChangeRequest::factory()->create([
        'school_id' => $admin->school_id,
        'requester_type' => 'student',
        'requester_uuid' => $student->uuid,
        'subject_type' => 'student',
        'subject_uuid' => $student->uuid,
        'field_key' => 'admission_number',
        'field_label' => 'Admission Number',
        'requested_value' => 'ADM-4321',
    ]);

    $this->actingAs($admin)
        ->get(route('profile-change-requests.index'))
        ->assertOk()
        ->assertSee('Admission Number')
        ->assertSee('ADM-4321');
});

test('approving a change request applies it to the real student record', function () {
    $admin = changeRequestSchoolAdmin();
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'admission_number' => 'ADM-0001']);
    $changeRequest = ProfileChangeRequest::factory()->create([
        'school_id' => $admin->school_id,
        'requester_type' => 'student',
        'requester_uuid' => $student->uuid,
        'subject_type' => 'student',
        'subject_uuid' => $student->uuid,
        'field_key' => 'admission_number',
        'field_label' => 'Admission Number',
        'current_value' => 'ADM-0001',
        'requested_value' => 'ADM-4321',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('profile-change-requests.approve', $changeRequest))
        ->assertRedirect();

    expect($student->fresh()->admission_number)->toBe('ADM-4321');
    expect($changeRequest->fresh()->status)->toBe('approved');
    expect($changeRequest->fresh()->reviewed_by)->toBe($admin->id);
});

test('approving a staff change request applies it to the real staff record', function () {
    $admin = changeRequestSchoolAdmin();
    $staff = Staff::factory()->create(['school_id' => $admin->school_id, 'staff_number' => 'STF-0001']);
    $changeRequest = ProfileChangeRequest::factory()->create([
        'school_id' => $admin->school_id,
        'requester_type' => 'staff',
        'requester_uuid' => $staff->uuid,
        'subject_type' => 'staff',
        'subject_uuid' => $staff->uuid,
        'field_key' => 'staff_number',
        'field_label' => 'Staff ID',
        'current_value' => 'STF-0001',
        'requested_value' => 'STF-4321',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('profile-change-requests.approve', $changeRequest))
        ->assertRedirect();

    expect($staff->fresh()->staff_number)->toBe('STF-4321');
});

test('rejecting a change request leaves the real record untouched', function () {
    $admin = changeRequestSchoolAdmin();
    $student = Student::factory()->create(['school_id' => $admin->school_id, 'admission_number' => 'ADM-0001']);
    $changeRequest = ProfileChangeRequest::factory()->create([
        'school_id' => $admin->school_id,
        'requester_type' => 'student',
        'requester_uuid' => $student->uuid,
        'subject_type' => 'student',
        'subject_uuid' => $student->uuid,
        'field_key' => 'admission_number',
        'current_value' => 'ADM-0001',
        'requested_value' => 'ADM-4321',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('profile-change-requests.reject', $changeRequest))
        ->assertRedirect();

    expect($student->fresh()->admission_number)->toBe('ADM-0001');
    expect($changeRequest->fresh()->status)->toBe('rejected');
});

test('a school admin cannot approve another school\'s change request', function () {
    $admin = changeRequestSchoolAdmin();
    $otherSchool = School::factory()->create();
    $changeRequest = ProfileChangeRequest::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($admin)
        ->post(route('profile-change-requests.approve', $changeRequest))
        ->assertForbidden();
});
