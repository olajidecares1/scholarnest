<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TimetableEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
});

test('a student can view their profile', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.profile', $this->school))
        ->assertStatus(200)
        ->assertSee($this->student->fullName());
});

test('a student\'s profile page does not expose their guardian\'s contact details', function () {
    $this->student->update([
        'guardian_name' => 'Ngozi Eze',
        'guardian_phone' => '08011112222',
        'guardian_email' => 'ngozi@example.com',
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.profile', $this->school))
        ->assertStatus(200)
        ->assertDontSee('Ngozi Eze')
        ->assertDontSee('08011112222')
        ->assertDontSee('ngozi@example.com');
});

test('a student sees assignments for their class with their own submission status', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'title' => 'Fractions Worksheet']);
    AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'student_id' => $this->student->id]);

    $otherClassAssignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'title' => 'Other Class Work']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.assignments.index', $this->school))
        ->assertStatus(200)
        ->assertSee('Fractions Worksheet')
        ->assertDontSee('Other Class Work');
});

test('a student sees only their own exam results', function () {
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);

    $otherStudent = Student::factory()->create(['school_id' => $this->school->id]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $otherStudent->id, 'score' => 40]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.results.index', $this->school))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertSee('85');
});

test('a student sees only their own attendance records', function () {
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'class_name' => 'JSS 1', 'date' => today()]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.attendance.index', $this->school))
        ->assertStatus(200);
});

test('a student can browse the library and see their own loans', function () {
    $book = Book::factory()->create(['school_id' => $this->school->id, 'title' => 'New General Mathematics']);
    BookLoan::factory()->create(['book_id' => $book->id, 'student_id' => $this->student->id]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.library.index', $this->school))
        ->assertStatus(200)
        ->assertSee('New General Mathematics');
});

test('a student has no route to view fee or invoice data', function () {
    Invoice::factory()->create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'title' => 'First Term Tuition']);

    expect(Route::has('student.fees.index'))->toBeFalse();
});

test('a student can view the settings page and update their contact details', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.settings.index', $this->school))
        ->assertStatus(200);

    $this->actingAs($this->student, 'student')
        ->put(route('student.settings.update-profile', $this->school), [
            'phone' => '08012345678',
        ])
        ->assertRedirect();

    expect($this->student->fresh()->phone)->toBe('08012345678');
});

test('a student cannot self-upload a profile photo', function () {
    $originalPhotoPath = $this->student->photo_path;

    $this->actingAs($this->student, 'student')
        ->put(route('student.settings.update-profile', $this->school), [
            'phone' => '08012345678',
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect();

    expect($this->student->fresh()->photo_path)->toBe($originalPhotoPath);
});

test('a student can view their own id card read-only but has no print or download route', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.id-card.show', $this->school))
        ->assertStatus(200);

    $this->actingAs($this->student, 'student')
        ->getJson(route('student.id-card.preview', $this->school))
        ->assertStatus(200)
        ->assertJsonStructure(['has_template', 'card_number', 'front', 'back']);

    expect(Route::has('student.id-card.print'))->toBeFalse();
    expect(Route::has('student.id-card.pdf'))->toBeFalse();
});

test('a student can request a change to a protected field but it is not applied until approved', function () {
    $this->actingAs($this->student, 'student')
        ->post(route('student.profile-change-requests.store', $this->school), [
            'field_key' => 'admission_number',
            'requested_value' => 'ADM-9999',
            'reason' => 'Typo on my original admission number.',
        ])
        ->assertRedirect();

    expect($this->student->fresh()->admission_number)->not->toBe('ADM-9999');
    $this->assertDatabaseHas('profile_change_requests', [
        'requester_type' => 'student',
        'requester_uuid' => $this->student->uuid,
        'field_key' => 'admission_number',
        'requested_value' => 'ADM-9999',
        'status' => 'pending',
    ]);
});

test('a student cannot request a change to a field outside the protected registry', function () {
    $this->actingAs($this->student, 'student')
        ->post(route('student.profile-change-requests.store', $this->school), [
            'field_key' => 'photo_path',
            'requested_value' => 'students/hacked.jpg',
        ])
        ->assertSessionHasErrors('field_key');
});

test('a student can change their password with the correct current password', function () {
    $this->actingAs($this->student, 'student')
        ->put(route('student.settings.update-password', $this->school), [
            'current_password' => 'password',
            'password' => 'NewSecure@123',
            'password_confirmation' => 'NewSecure@123',
        ])
        ->assertRedirect();

    $this->assertTrue(Hash::check('NewSecure@123', $this->student->fresh()->password));
});

test('changing password with the wrong current password fails', function () {
    $this->actingAs($this->student, 'student')
        ->put(route('student.settings.update-password', $this->school), [
            'current_password' => 'wrong-password',
            'password' => 'NewSecure@123',
            'password_confirmation' => 'NewSecure@123',
        ])
        ->assertSessionHasErrors('current_password');
});

test('a student sees subjects derived from their class assignments and exam results', function () {
    $assignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'subject' => 'Mathematics']);
    AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'student_id' => $this->student->id]);

    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English Language', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 72]);

    $otherClassAssignment = Assignment::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject' => 'Chemistry']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.subjects', $this->school))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertSee('English Language')
        ->assertSee('72%')
        ->assertDontSee('Chemistry');
});

test('a student sees only their own class\'s timetable entries', function () {
    TimetableEntry::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'day_of_week' => 1, 'subject' => 'Mathematics']);
    TimetableEntry::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'day_of_week' => 1, 'subject' => 'Chemistry']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.timetable', $this->school))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertDontSee('Chemistry');
});

test('a student can view the help page', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.help.index', $this->school))
        ->assertStatus(200)
        ->assertSee($this->school->name);
});

test('a student cannot browse another school\'s portal by visiting its slug', function () {
    $otherSchool = School::factory()->create();

    $this->actingAs($this->student, 'student')
        ->get(route('student.dashboard', $otherSchool))
        ->assertStatus(404);
});
