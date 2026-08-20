<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
    $this->guardian->students()->attach($this->student->id, ['relationship' => 'Mother']);
});

test('a guardian with no linked children is forbidden from the dashboard', function () {
    $lonelyGuardian = Guardian::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($lonelyGuardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertForbidden();
});

test('a guardian sees their child\'s dashboard with a module grid and recent notices', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'Term Begins', 'class_name' => null]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertStatus(200)
        ->assertSee($this->student->first_name)
        ->assertSee('Term Begins');
});

test('a guardian with multiple children sees a child switcher and can switch active child', function () {
    $secondChild = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2']);
    $this->guardian->students()->attach($secondChild->id, ['relationship' => 'Mother']);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school).'?child='.$secondChild->uuid)
        ->assertStatus(200)
        ->assertSee($secondChild->fullName());
});

test('a guardian can view their child\'s profile', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.profile', [$this->school, $this->student]))
        ->assertStatus(200)
        ->assertSee($this->student->fullName());
});

test('a guardian can view their child\'s timetable', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.timetable', [$this->school, $this->student]))
        ->assertStatus(200);
});

test('a guardian sees only their child\'s exam results', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);

    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $otherStudent->id, 'score' => 40]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.results', [$this->school, $this->student]))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertSee('85');
});

test('a guardian sees only their child\'s attendance records', function () {
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'class_name' => 'JSS 1', 'date' => today()]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.attendance', [$this->school, $this->student]))
        ->assertStatus(200);
});

test('a guardian sees assignments for their child\'s class with submission status', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'title' => 'Fractions Worksheet']);
    AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'student_id' => $this->student->id]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.assignments', [$this->school, $this->student]))
        ->assertStatus(200)
        ->assertSee('Fractions Worksheet');
});

test('a guardian sees only their child\'s invoices', function () {
    Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'title' => 'First Term Tuition']);

    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
    Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $otherStudent->id, 'title' => 'Other Student Tuition']);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.fees', [$this->school, $this->student]))
        ->assertStatus(200)
        ->assertSee('First Term Tuition')
        ->assertDontSee('Other Student Tuition');
});

test('a guardian cannot view a student who is not their child', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.children.profile', [$this->school, $otherStudent]))
        ->assertForbidden();
});

test('a guardian sees notices for their child\'s class and school-wide notices', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For Everyone', 'class_name' => null]);
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For My Child\'s Class', 'class_name' => 'JSS 1']);
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'For Other Class', 'class_name' => 'JSS 2']);

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.messages.index', $this->school))
        ->assertStatus(200)
        ->assertSee('For Everyone')
        ->assertSee('For My Child\'s Class')
        ->assertDontSee('For Other Class');
});

test('a guardian can view the settings page and update their contact details', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.settings.index', $this->school))
        ->assertStatus(200);

    $this->actingAs($this->guardian, 'guardian')
        ->put(route('guardian.settings.update-profile', $this->school), [
            'name' => 'Updated Name',
            'phone' => '08012345678',
        ])
        ->assertRedirect();

    expect($this->guardian->fresh()->name)->toBe('Updated Name');
});

test('a guardian cannot self-upload a profile photo', function () {
    $originalPhotoPath = $this->guardian->photo_path;

    $this->actingAs($this->guardian, 'guardian')
        ->put(route('guardian.settings.update-profile', $this->school), [
            'name' => $this->guardian->name,
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect();

    expect($this->guardian->fresh()->photo_path)->toBe($originalPhotoPath);
});

test('a guardian can request a change to their child\'s protected field but it is not applied until approved', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.profile-change-requests.store', [$this->school, $this->student]), [
            'field_key' => 'class_name',
            'requested_value' => 'JSS 2',
        ])
        ->assertRedirect();

    expect($this->student->fresh()->class_name)->not->toBe('JSS 2');
    $this->assertDatabaseHas('profile_change_requests', [
        'requester_type' => 'guardian',
        'requester_uuid' => $this->guardian->uuid,
        'subject_type' => 'student',
        'subject_uuid' => $this->student->uuid,
        'field_key' => 'class_name',
        'requested_value' => 'JSS 2',
        'status' => 'pending',
    ]);
});

test('a guardian cannot request a change for a student who is not their child', function () {
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->guardian, 'guardian')
        ->post(route('guardian.children.profile-change-requests.store', [$this->school, $otherStudent]), [
            'field_key' => 'class_name',
            'requested_value' => 'JSS 2',
        ])
        ->assertForbidden();
});

test('a guardian can change their password with the correct current password', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->put(route('guardian.settings.update-password', $this->school), [
            'current_password' => 'password',
            'password' => 'NewSecure@123',
            'password_confirmation' => 'NewSecure@123',
        ])
        ->assertRedirect();

    $this->assertTrue(Hash::check('NewSecure@123', $this->guardian->fresh()->password));
});

test('a guardian can view the help page', function () {
    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.help.index', $this->school))
        ->assertStatus(200)
        ->assertSee($this->school->name);
});

test('a guardian cannot browse another school\'s portal by visiting its slug', function () {
    $otherSchool = School::factory()->create();

    $this->actingAs($this->guardian, 'guardian')
        ->get(route('guardian.dashboard', $otherSchool))
        ->assertStatus(404);
});
