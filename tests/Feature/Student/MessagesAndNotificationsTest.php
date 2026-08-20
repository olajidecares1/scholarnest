<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Assignment;
use App\Models\CoCurricularActivity;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Student;
use App\Models\Subscription;
use App\Notifications\NewAssignmentPosted;
use App\Notifications\NewNoticePosted;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
});

test('a student sees notices sent to all classes and their own class', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For Everyone', 'class_name' => null]);
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For My Class', 'class_name' => 'JSS 1']);
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For Other Class', 'class_name' => 'JSS 2']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.messages.index', $this->school))
        ->assertStatus(200)
        ->assertSee('For Everyone')
        ->assertSee('For My Class')
        ->assertDontSee('For Other Class');
});

test('viewing a notice marks it as read', function () {
    $notice = SchoolNotice::factory()->create(['school_id' => $this->school->id, 'class_name' => null]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.messages.show', [$this->school, $notice]))
        ->assertStatus(200);

    $this->assertDatabaseHas('school_notice_reads', [
        'school_notice_id' => $notice->id,
        'student_id' => $this->student->id,
    ]);
});

test('a student cannot view a notice targeted at another class', function () {
    $notice = SchoolNotice::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.messages.show', [$this->school, $notice]))
        ->assertForbidden();
});

test('sending a notice notifies the targeted students', function () {
    $notice = SchoolNotice::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $this->student->notify(new NewNoticePosted($notice));

    $this->actingAs($this->student, 'student')
        ->get(route('student.notifications.index', $this->school))
        ->assertStatus(200)
        ->assertSee($notice->title);

    expect($this->student->fresh()->notifications()->whereNull('read_at')->count())->toBe(0);
});

test('a student sees co-curricular activities and can join and leave', function () {
    $activity = CoCurricularActivity::factory()->create(['school_id' => $this->school->id, 'name' => 'Debate Club']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.co-curricular.index', $this->school))
        ->assertStatus(200)
        ->assertSee('Debate Club');

    $this->actingAs($this->student, 'student')
        ->post(route('student.co-curricular.join', [$this->school, $activity]))
        ->assertRedirect();

    expect($this->student->coCurricularActivities()->where('co_curricular_activities.id', $activity->id)->exists())->toBeTrue();

    $this->actingAs($this->student, 'student')
        ->delete(route('student.co-curricular.leave', [$this->school, $activity]))
        ->assertRedirect();

    expect($this->student->coCurricularActivities()->where('co_curricular_activities.id', $activity->id)->exists())->toBeFalse();
});

test('creating an assignment notifies students in that class', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'title' => 'Algebra Homework']);

    $this->student->notify(new NewAssignmentPosted($assignment));

    expect($this->student->fresh()->unreadNotifications()->count())->toBe(1);
});
