<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Examination;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Notifications\ResultAvailableNotification;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $this->guardian->students()->attach($this->student->id, ['relationship' => 'Mother']);
});

test('a guardian sees notifications sent to them and viewing the index marks them read', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->guardian->notify(new ResultAvailableNotification($examination, $this->student));

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.notifications.index', $this->school))
        ->assertStatus(200)
        ->assertSee($this->student->fullName());

    expect($this->guardian->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a guardian only sees their own notifications, not another guardian\'s', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $otherGuardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $otherGuardian->notify(new ResultAvailableNotification($examination, $this->student));

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.notifications.index', $this->school))
        ->assertStatus(200);

    expect($this->guardian->fresh()->notifications()->count())->toBe(0);
});
