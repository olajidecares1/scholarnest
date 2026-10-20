<?php

use App\Enums\AttendanceStatus;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSummary;
use App\Models\Examination;
use App\Models\RepositoryResult;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ReportCardData;

/**
 * Manual attendance: a term's totals entered per student, optional per
 * school, on every plan, and never touching the daily register.
 */
function manualAttendanceSchool(PlanKey $planKey = PlanKey::Basic, bool $enabled = true, string $code = 'MAN'): array
{
    $school = School::factory()->create([
        'school_code' => $code,
        'current_session' => '2025/2026',
        'manual_attendance_enabled' => $enabled,
    ]);
    activateSchool($school, $planKey);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    TeacherAssignment::create([
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

    AcademicTerm::create([
        'school_id' => $school->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
        'starts_on' => now()->subDays(30)->toDateString(),
        'ends_on' => now()->toDateString(),
        'school_days' => 60,
    ]);

    return [$school->fresh(), $teacher, $student];
}

/**
 * @return array<string, mixed>
 */
function manualEntry(Student $student, int $present, int $absent = 0, int $late = 0, int $excused = 0): array
{
    return [
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
        'entries' => [
            $student->id => [
                'days_present' => $present,
                'days_absent' => $absent,
                'days_late' => $late,
                'days_excused' => $excused,
            ],
        ],
    ];
}

test('a class teacher can enter term totals on every plan', function (PlanKey $planKey) {
    [$school, $teacher, $student] = manualAttendanceSchool($planKey, code: substr(md5($planKey->value), 0, 6));

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.manual', [$school, 'session' => '2025/2026', 'term' => 'second']))
        ->assertOk()
        ->assertSee($student->fullName())
        ->assertSee('School days this term');

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, 52, 4, 3, 1))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $summary = AttendanceSummary::sole();

    expect($summary->school_id)->toBe($school->id)
        ->and($summary->days_present)->toBe(52)
        ->and($summary->totalDays())->toBe(60)
        ->and($summary->recorded_by_staff_id)->toBe($teacher->id)
        ->and($summary->discrepancyAgainst(60))->toBeNull();
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a teacher can correct totals they entered earlier', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.manual.store', $school), manualEntry($student, 50, 10));
    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.manual.store', $school), manualEntry($student, 55, 5));

    expect(AttendanceSummary::count())->toBe(1)
        ->and(AttendanceSummary::sole()->only(['days_present', 'days_absent']))->toBe(['days_present' => 55, 'days_absent' => 5]);
});

test('totals that disagree with the term\'s school days are flagged', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, 40, 5))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 do not match'));

    expect(AttendanceSummary::sole()->discrepancyAgainst(60))->toBe('short')
        ->and(AttendanceSummary::discrepancyBetween(61, 60))->toBe('over')
        ->and(AttendanceSummary::discrepancyBetween(10, null))->toBeNull();
});

test('negative or non-numeric figures are rejected', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, -1))
        ->assertSessionHasErrors();

    expect(AttendanceSummary::count())->toBe(0);
});

test('a teacher cannot enter totals for a class that is not theirs', function () {
    [$school, $teacher] = manualAttendanceSchool();

    $outsider = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 5', 'is_active' => true]);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), [...manualEntry($outsider, 60), 'class_name' => 'Primary 5'])
        ->assertSessionHasErrors('class_name');

    // Smuggled into the teacher's own class request instead: skipped.
    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($outsider, 60));

    expect(AttendanceSummary::count())->toBe(0);
});

test('a teacher cannot change totals once the student\'s card is published, but the School Admin can', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();

    AttendanceSummary::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
        'days_present' => 50,
    ]);

    RepositoryResult::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
        'payload' => [],
        'source_fingerprint' => str_repeat('a', 64),
        'pushed_by_name' => 'Admin',
        'pushed_at' => now(),
    ]);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, 58))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'already published'));

    expect(AttendanceSummary::sole()->days_present)->toBe(50);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->post(route('attendance.manual.store'), manualEntry($student, 58))
        ->assertSessionHasNoErrors();

    expect(AttendanceSummary::sole()->days_present)->toBe(58)
        ->and(AttendanceSummary::sole()->recorded_by)->toBe($admin->id);
});

test('the feature is off until the school switches it on', function () {
    [$school, $teacher, $student] = manualAttendanceSchool(enabled: false);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.manual', $school))
        ->assertRedirect(route('staff.attendance.index', $school));

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, 60))
        ->assertForbidden();

    // And the daily register is untouched by the option either way.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.index', $school))
        ->assertOk()
        ->assertDontSee('Term Totals');
});

test('the School Admin switches it on in Settings and sets school days per term', function () {
    [$school] = manualAttendanceSchool(enabled: false);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->put(route('settings.update'), [
        'name' => $school->name,
        'timezone' => 'Africa/Lagos',
        'manual_attendance_enabled' => '1',
    ])->assertSessionHasNoErrors();

    expect($school->fresh()->manual_attendance_enabled)->toBeTrue();

    $this->actingAs($admin)->post(route('academics.terms.store'), [
        'session' => '2025/2026',
        'term' => ExamTerm::Third->value,
        'starts_on' => now()->addMonth()->toDateString(),
        'ends_on' => now()->addMonths(3)->toDateString(),
        'school_days' => 58,
    ])->assertSessionHasNoErrors();

    expect(AcademicTerm::where('term', ExamTerm::Third)->sole()->school_days)->toBe(58);
});

test('the admin page renders on every plan', function (PlanKey $planKey) {
    [$school, , $student] = manualAttendanceSchool($planKey, code: substr(md5('a'.$planKey->value), 0, 6));
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->get(route('attendance.manual', ['class' => 'Primary 6', 'session' => '2025/2026', 'term' => 'second']))
        ->assertOk()
        ->assertSee($student->fullName());
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('blanks can be filled from the daily register without saving', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();

    AttendanceRecord::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'date' => now()->subDays(2)->toDateString(),
        'status' => AttendanceStatus::Late,
    ]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.manual', [$school, 'session' => '2025/2026', 'term' => 'second', 'prefill' => 1]))
        ->assertOk()
        ->assertSee('From daily register, not saved yet');

    expect(AttendanceSummary::count())->toBe(0);
});

test('entered totals appear on the report card in place of the register', function () {
    [$school, , $student] = manualAttendanceSchool();

    // The register says one absence; the teacher's totals say otherwise.
    AttendanceRecord::create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'class_name' => 'Primary 6',
        'date' => now()->subDays(2)->toDateString(),
        'status' => AttendanceStatus::Absent,
    ]);

    AttendanceSummary::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
        'days_present' => 54,
        'days_absent' => 3,
        'days_late' => 3,
        'days_excused' => 0,
    ]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Primary 6',
        'session' => '2025/2026',
        'term' => ExamTerm::Second->value,
    ]);

    $attendance = ReportCardData::for($examination, $student)['attendance'];

    expect($attendance['present'])->toBe(54)
        ->and($attendance['total'])->toBe(60)
        ->and($attendance['school_days'])->toBe(60)
        ->and($attendance['percent'])->toBe(95)
        ->and($attendance['source'])->toBe('manual');

    // Switched off, the same card goes back to the register.
    $school->update(['manual_attendance_enabled' => false]);

    $attendance = ReportCardData::for($examination, $student->fresh())['attendance'];

    expect($attendance['source'])->toBe('register')
        ->and($attendance['absent'])->toBe(1);
});
