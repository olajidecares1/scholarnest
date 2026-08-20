<?php

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TeacherAssignment;
use App\Support\AcademicSession;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 1']);
});

test('a class teacher sees the students in their assigned class', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'first_name' => 'Amaka', 'last_name' => 'Obi', 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'first_name' => 'Tunde', 'last_name' => 'Bello', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.attendance.index', $this->school))
        ->assertOk()
        ->assertSee('Amaka Obi')
        ->assertDontSee('Tunde Bello');
});

test('a teacher with no class teacher assignment sees a friendly empty state, not an error', function () {
    $unassigned = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($unassigned, 'staff')
        ->get(route('staff.attendance.index', $this->school))
        ->assertOk()
        ->assertSee("haven't been assigned as a Class Teacher", false);
});

test('a non-teacher staff member cannot access attendance', function () {
    $nonTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::SupportStaff]);

    $this->actingAs($nonTeacher, 'staff')
        ->get(route('staff.attendance.index', $this->school))
        ->assertForbidden();
});

test('a class teacher can save attendance for their class', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.attendance.store', $this->school), [
            'class_name' => 'JSS 1',
            'date' => today()->toDateString(),
            'records' => [$student->id => AttendanceStatus::Present->value],
        ])
        ->assertRedirect();

    $record = AttendanceRecord::where('student_id', $student->id)->firstOrFail();
    expect($record->status)->toBe(AttendanceStatus::Present);
    expect($record->marked_by_staff_id)->toBe($this->teacher->id);
    expect($record->marked_by)->toBeNull();
});

test('a class teacher cannot mark attendance for a student outside their class', function () {
    $outsideStudent = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.attendance.store', $this->school), [
            'class_name' => 'JSS 1',
            'date' => today()->toDateString(),
            'records' => [$outsideStudent->id => AttendanceStatus::Present->value],
        ]);

    expect(AttendanceRecord::where('student_id', $outsideStudent->id)->exists())->toBeFalse();
});

test('a class teacher cannot submit attendance for a class they are not assigned to', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.attendance.store', $this->school), [
            'class_name' => 'JSS 2',
            'date' => today()->toDateString(),
            'records' => [$student->id => AttendanceStatus::Present->value],
        ])
        ->assertSessionHasErrors('class_name');
});

test('a teacher who is Class Teacher of multiple classes can switch between them', function () {
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 2']);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true, 'first_name' => 'Tunde', 'last_name' => 'Bello']);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.attendance.index', [$this->school, 'class' => 'JSS 2']))
        ->assertOk()
        ->assertSee('Tunde Bello');
});

test('weekly attendance history shows aggregated counts', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'first_name' => 'Amaka', 'last_name' => 'Obi']);

    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'JSS 1', 'date' => today(), 'status' => AttendanceStatus::Present]);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'JSS 1', 'date' => today()->subDay(), 'status' => AttendanceStatus::Absent]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.attendance.history', [$this->school, 'period' => 'week']))
        ->assertOk()
        ->assertSee('Amaka Obi');
});

test('termly attendance shows a notice when term dates are not configured', function () {
    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.attendance.history', [$this->school, 'period' => 'term']))
        ->assertOk()
        ->assertSee("Term dates haven't been set", false);
});

test('termly attendance uses the configured term range once set', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    AcademicTerm::factory()->create([
        'school_id' => $this->school->id,
        'session' => AcademicSession::current(),
        'term' => ExamTerm::First,
        'starts_on' => today()->subDays(10),
        'ends_on' => today()->addDays(10),
    ]);
    AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'JSS 1', 'date' => today(), 'status' => AttendanceStatus::Present]);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.attendance.history', [$this->school, 'period' => 'term']))
        ->assertOk()
        ->assertSee('Amaka Obi')
        ->assertDontSee("Term dates haven't been set", false);
});
