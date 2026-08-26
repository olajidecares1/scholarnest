<?php

use App\Enums\MemorandumAudience;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewNoticePosted;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can send a memorandum to all students', function () {
    Student::factory()->count(2)->create(['school_id' => $this->school->id, 'is_active' => true]);

    $response = $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'Mid-Term Break',
        'body' => 'School closes on Friday.',
        'audience' => MemorandumAudience::Students->value,
    ]);

    $response->assertRedirect();

    $notice = SchoolNotice::where('title', 'Mid-Term Break')->firstOrFail();
    expect($notice->school_id)->toBe($this->school->id);
    expect($notice->sent_by)->toBe($this->admin->id);
    expect($notice->audience)->toBe(MemorandumAudience::Students);
});

test('a school admin can target a memorandum to one class', function () {
    $response = $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'JSS 1 Excursion',
        'body' => 'Bring your permission slip.',
        'audience' => MemorandumAudience::Students->value,
        'class_name' => 'JSS 1',
    ]);

    $response->assertRedirect();

    $notice = SchoolNotice::where('title', 'JSS 1 Excursion')->firstOrFail();
    expect($notice->class_name)->toBe('JSS 1');
});

test('a school admin only sees memorandums from their own school', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Notice']);
    $otherSchool = School::factory()->create();
    SchoolNotice::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Notice']);

    $this->actingAs($this->admin)
        ->get(route('notices.index'))
        ->assertSee('Own Notice')
        ->assertDontSee('Other Notice');
});

// -----------------------------------------------------------------------------
// Who it reaches
// -----------------------------------------------------------------------------

test('a memorandum reaches the group it was addressed to, and only that group', function (MemorandumAudience $audience, string $expected) {
    Notification::fake();

    $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($student->id);

    $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'A Memorandum',
        'body' => 'Please read.',
        'audience' => $audience->value,
    ])->assertSessionHasNoErrors();

    $recipients = ['staff' => $staff, 'student' => $student, 'guardian' => $guardian];

    foreach ($recipients as $key => $recipient) {
        if ($key === $expected) {
            Notification::assertSentTo($recipient, NewNoticePosted::class);
        } else {
            Notification::assertNotSentTo($recipient, NewNoticePosted::class);
        }
    }
})->with([
    'staff' => [MemorandumAudience::Staff, 'staff'],
    'students' => [MemorandumAudience::Students, 'student'],
    'guardians' => [MemorandumAudience::Guardians, 'guardian'],
]);

test('one memorandum addressed to everyone reaches all three groups', function () {
    Notification::fake();

    $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($student->id);

    // One memorandum, not three written out separately - which is the point of
    // the option, and is how it should still read a term later.
    $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'Speech Day',
        'body' => 'Everyone is invited.',
        'audience' => MemorandumAudience::All->value,
    ])->assertSessionHasNoErrors();

    expect(SchoolNotice::where('title', 'Speech Day')->count())->toBe(1);

    Notification::assertSentTo($staff, NewNoticePosted::class);
    Notification::assertSentTo($student, NewNoticePosted::class);
    Notification::assertSentTo($guardian, NewNoticePosted::class);
});

test('a class filter narrows students and their parents, but not staff', function () {
    Notification::fake();

    $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $inClass = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $elsewhere = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);

    $theirParent = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $theirParent->students()->attach($inClass->id);

    $otherParent = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $otherParent->students()->attach($elsewhere->id);

    $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'JSS 1 Trip',
        'body' => 'Permission slips due Friday.',
        'audience' => MemorandumAudience::All->value,
        'class_name' => 'JSS 1',
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($inClass, NewNoticePosted::class);
    Notification::assertSentTo($theirParent, NewNoticePosted::class);
    Notification::assertNotSentTo($elsewhere, NewNoticePosted::class);
    Notification::assertNotSentTo($otherParent, NewNoticePosted::class);

    // Staff do not belong to a class, so narrowing by one does not exclude
    // them - they are told about the trip either way.
    Notification::assertSentTo($staff, NewNoticePosted::class);
});

// -----------------------------------------------------------------------------
// Every plan, within what that plan actually has
// -----------------------------------------------------------------------------

test('every plan can send a memorandum to its staff', function (PlanKey $planKey) {
    Notification::fake();

    $school = School::factory()->create();
    activateSchool($school, $planKey);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $staff = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($admin)->post(route('notices.store'), [
        'title' => 'Staff Meeting',
        'body' => 'Thursday, 4pm.',
        'audience' => MemorandumAudience::Staff->value,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($staff, NewNoticePosted::class);
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a basic school is not offered groups it has no accounts for', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // Offering "Parents / Guardians" here would be offering to send a
    // memorandum to accounts that do not exist on this plan.
    $this->actingAs($admin)
        ->get(route('notices.index'))
        ->assertOk()
        ->assertSee('Teachers &amp; Staff', false)
        ->assertSee('Everyone')
        ->assertDontSee('Parents / Guardians');

    $this->actingAs($admin)->post(route('notices.store'), [
        'title' => 'Nope',
        'body' => 'Should not send.',
        'audience' => MemorandumAudience::Guardians->value,
    ])->assertSessionHasErrors('audience');
});

test('everyone at a basic school means the people that school has', function () {
    Notification::fake();

    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $staff = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => true]);

    $this->actingAs($admin)->post(route('notices.store'), [
        'title' => 'Everyone',
        'body' => 'Hello.',
        'audience' => MemorandumAudience::All->value,
    ])->assertSessionHasNoErrors();

    // A Basic school has no student portal, so a memorandum to "everyone"
    // reaches the people it can actually reach.
    Notification::assertSentTo($staff, NewNoticePosted::class);
    Notification::assertNotSentTo($student, NewNoticePosted::class);
});

test('a memorandum never crosses into another school', function () {
    Notification::fake();

    $otherSchool = School::factory()->create();
    activateSchool($otherSchool);
    $theirStaff = Staff::factory()->create(['school_id' => $otherSchool->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $ourStaff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'Ours Only',
        'body' => 'Internal.',
        'audience' => MemorandumAudience::All->value,
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo($ourStaff, NewNoticePosted::class);
    Notification::assertNotSentTo($theirStaff, NewNoticePosted::class);
});
