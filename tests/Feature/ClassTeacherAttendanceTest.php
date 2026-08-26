<?php

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment as Assignment;
use App\Models\User;
use App\Services\ReportCardData;

/**
 * The class teacher's register, and where it ends up.
 *
 * Taking attendance is part of running a school's own academics, so it is on
 * every plan - a Basic school's teachers need it exactly as much as anyone
 * else's. What the teacher records is then counted onto the report card
 * automatically: they enter presence, never a percentage.
 */
function attendanceSchool(PlanKey $planKey = PlanKey::Basic, string $code = 'ATT'): array
{
    $school = School::factory()->create(['school_code' => $code, 'current_session' => '2025/2026']);
    activateSchool($school, $planKey);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    Assignment::create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'type' => TeacherAssignmentType::ClassTeacher->value,
        'class_name' => 'Primary 6',
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'is_active' => true,
    ]);

    return [$school->fresh(), $teacher, $student];
}

// -----------------------------------------------------------------------------
// Available on every plan
// -----------------------------------------------------------------------------

test('a class teacher can reach the register on every plan', function (PlanKey $planKey) {
    [$school, $teacher] = attendanceSchool($planKey, substr(md5($planKey->value), 0, 6));

    // Taking the register is the school's own academic work, not a premium
    // extra, so it is not plan-gated.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.index', $school))
        ->assertOk();
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a class teacher records attendance against the right school and class', function () {
    [$school, $teacher, $student] = attendanceSchool();

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
        'class_name' => 'Primary 6',
        'date' => now()->toDateString(),
        'records' => [$student->id => AttendanceStatus::Present->value],
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('attendance_records', [
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'status' => AttendanceStatus::Present->value,
    ]);
});

test('a teacher cannot mark a class that is not theirs', function () {
    [$school, $teacher] = attendanceSchool();

    $otherClassStudent = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 5',
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.store', $school), [
            'class_name' => 'Primary 5',
            'date' => now()->toDateString(),
            'records' => [$otherClassStudent->id => AttendanceStatus::Present->value],
        ])
        ->assertSessionHasErrors('class_name');

    expect(AttendanceRecord::count())->toBe(0);
});

test('a student outside the teacher\'s class is skipped even if smuggled into the request', function () {
    [$school, $teacher, $student] = attendanceSchool();

    $outsider = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 5',
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
        'class_name' => 'Primary 6',
        'date' => now()->toDateString(),
        'records' => [
            $student->id => AttendanceStatus::Present->value,
            $outsider->id => AttendanceStatus::Absent->value,
        ],
    ]);

    // The class is taken from the teacher's own assignment, and every student
    // in the request is checked against it.
    expect(AttendanceRecord::pluck('student_id')->all())->toBe([$student->id]);
});

// -----------------------------------------------------------------------------
// Counted onto the report card, automatically
// -----------------------------------------------------------------------------

test('the register a teacher takes appears on the report card as a percentage', function () {
    [$school, $teacher, $student] = attendanceSchool();

    AcademicTerm::create([
        'school_id' => $school->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
        'starts_on' => now()->subDays(20)->toDateString(),
        'ends_on' => now()->toDateString(),
    ]);

    // Eight present, two absent - the teacher records presence, never a
    // percentage.
    foreach (range(1, 10) as $day) {
        $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
            'class_name' => 'Primary 6',
            'date' => now()->subDays($day)->toDateString(),
            'records' => [$student->id => $day <= 8 ? AttendanceStatus::Present->value : AttendanceStatus::Absent->value],
        ]);
    }

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 80,
        'test_score' => 32,
        'exam_score' => 48,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk()
        ->assertSee('80%')
        ->assertSee('Days Present')
        ->assertSee('Days Absent');
});

test('the card counts the examination\'s own term and no other', function () {
    [$school, $teacher, $student] = attendanceSchool();

    AcademicTerm::create([
        'school_id' => $school->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
        'starts_on' => now()->subDays(10)->toDateString(),
        'ends_on' => now()->toDateString(),
    ]);

    // Inside the term.
    AttendanceRecord::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'date' => now()->subDays(5)->toDateString(),
        'status' => AttendanceStatus::Present,
    ]);

    // Last term, which this card must not count.
    AttendanceRecord::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'date' => now()->subDays(120)->toDateString(),
        'status' => AttendanceStatus::Absent,
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    $data = ReportCardData::for($examination, $student);

    expect($data['attendance']['total'])->toBe(1)
        ->and($data['attendance']['present'])->toBe(1)
        ->and($data['attendance']['absent'])->toBe(0)
        ->and($data['attendance']['percent'])->toBe(100);
});

test('one school\'s attendance never reaches another school\'s report card', function () {
    [$schoolA, , $studentA] = attendanceSchool(PlanKey::Basic, 'AAAA');
    [$schoolB, , $studentB] = attendanceSchool(PlanKey::Basic, 'BBBB');

    foreach ([[$schoolA, $studentA, AttendanceStatus::Present], [$schoolB, $studentB, AttendanceStatus::Absent]] as [$school, $student, $status]) {
        AcademicTerm::create([
            'school_id' => $school->id,
            'session' => '2025/2026',
            'term' => ExamTerm::Second->value,
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->toDateString(),
        ]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'class_name' => 'Primary 6',
            'date' => now()->subDays(2)->toDateString(),
            'status' => $status,
        ]);
    }

    $examinationA = Examination::factory()->create([
        'school_id' => $schoolA->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    // A's student was present every day recorded; B's was absent. Neither
    // figure may leak into the other's card.
    $data = ReportCardData::for($examinationA, $studentA);

    expect($data['attendance']['total'])->toBe(1)
        ->and($data['attendance']['percent'])->toBe(100);
});

test('a school with no term dates is told why attendance is blank', function () {
    [$school, , $student] = attendanceSchool();

    AttendanceRecord::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'date' => now()->subDay()->toDateString(),
        'status' => AttendanceStatus::Present,
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    // The register is full. Without term dates there is no window to count it
    // within, and a blank column reads as "nobody took the register" unless
    // the card says otherwise.
    $this->actingAs($admin)
        ->get(route('results.print', [$examination, $student]))
        ->assertOk()
        ->assertSee('Term dates not set')
        ->assertSee('Academics');
});
