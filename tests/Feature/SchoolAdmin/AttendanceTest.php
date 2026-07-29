<?php

use App\Enums\AttendanceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can view the take attendance page with their active students', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Inactive', 'last_name' => 'Kid', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('attendance.index'))
        ->assertStatus(200)
        ->assertSee('Amaka Obi')
        ->assertDontSee('Inactive Kid');
});

test('a school admin can save attendance for their students', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);

    $response = $this->actingAs($this->admin)->post(route('attendance.store'), [
        'date' => today()->toDateString(),
        'records' => [
            $student->id => AttendanceStatus::Present->value,
        ],
    ]);

    $response->assertRedirect();

    $record = AttendanceRecord::where('student_id', $student->id)->firstOrFail();
    expect($record->school_id)->toBe($this->school->id);
    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->class_name)->toBe('JSS 1');
});

test('saving attendance twice for the same day updates the existing record instead of duplicating', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('attendance.store'), [
        'date' => today()->toDateString(),
        'records' => [$student->id => AttendanceStatus::Present->value],
    ]);

    $this->actingAs($this->admin)->post(route('attendance.store'), [
        'date' => today()->toDateString(),
        'records' => [$student->id => AttendanceStatus::Absent->value],
    ]);

    expect(AttendanceRecord::where('student_id', $student->id)->count())->toBe(1);
    expect(AttendanceRecord::where('student_id', $student->id)->first()->status)->toBe(AttendanceStatus::Absent);
});

test('a school admin cannot mark attendance for another school\'s student', function () {
    $otherSchool = School::factory()->create();
    $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)->post(route('attendance.store'), [
        'date' => today()->toDateString(),
        'records' => [$otherStudent->id => AttendanceStatus::Present->value],
    ]);

    expect(AttendanceRecord::where('student_id', $otherStudent->id)->exists())->toBeFalse();
});

test('a school admin can view attendance history filtered by class', function () {
    $studentA = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi', 'class_name' => 'JSS 1']);
    $studentB = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Tunde', 'last_name' => 'Bello', 'class_name' => 'SS 2']);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $studentA->id, 'class_name' => 'JSS 1']);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $studentB->id, 'class_name' => 'SS 2']);

    $this->actingAs($this->admin)
        ->get(route('attendance.history', ['class' => 'JSS 1']))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('a school admin only sees attendance history from their own school', function () {
    $otherSchool = School::factory()->create();
    $ownStudent = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id, 'first_name' => 'Tunde', 'last_name' => 'Bello']);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $ownStudent->id]);
    AttendanceRecord::factory()->create(['school_id' => $otherSchool->id, 'student_id' => $otherStudent->id]);

    $this->actingAs($this->admin)
        ->get(route('attendance.history'))
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('the dashboard shows real attendance stats for this week', function () {
    Subscription::factory()->create(['school_id' => $this->school->id, 'status' => SubscriptionStatus::Active]);
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'date' => today(), 'status' => AttendanceStatus::Present]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertSee('100.0%')
        ->assertSee('Attendance (This Week)');
});
