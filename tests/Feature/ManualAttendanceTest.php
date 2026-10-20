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

test('manual attendance is on for a new school by default', function () {
    expect(School::factory()->create()->fresh()->hasManualAttendance())->toBeTrue();
});

test('when disabled, every web page and save is refused with 403 and every link is hidden', function () {
    [$school, $teacher, $student] = manualAttendanceSchool(enabled: false);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.manual', $school))
        ->assertForbidden()
        ->assertSee('This feature has been disabled for your school.');

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.manual.store', $school), manualEntry($student, 60))
        ->assertForbidden();

    $this->actingAs($admin)->get(route('attendance.manual'))->assertForbidden();
    $this->actingAs($admin)->post(route('attendance.manual.store'), manualEntry($student, 60))->assertForbidden();

    expect(AttendanceSummary::count())->toBe(0);

    // The daily register keeps working, with no way into the hidden feature.
    $this->actingAs($teacher, 'staff')->get(route('staff.attendance.index', $school))->assertOk()->assertDontSee('Term Totals');
    $this->actingAs($teacher, 'staff')->get(route('staff.attendance.history', $school))->assertOk()->assertDontSee('Term Totals');
    $this->actingAs($admin)->get(route('attendance.index'))->assertOk()->assertDontSee('Term Totals');
    $this->actingAs($admin)->get(route('attendance.history'))->assertOk()->assertDontSee('Term Totals');
});

test('the Super Admin switches it off and on per school, keeping every record', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();
    [$otherSchool] = manualAttendanceSchool(code: 'OTHER');
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    AttendanceSummary::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'session' => '2025/2026',
        'term' => ExamTerm::Second,
        'days_present' => 57,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('super-admin.schools.show', $school))
        ->assertOk()
        ->assertSee('Disable Manual Attendance');

    $this->actingAs($superAdmin)
        ->post(route('super-admin.schools.manual-attendance', $school), ['enabled' => 0])
        ->assertRedirect()
        ->assertSessionHas('status', "Manual attendance has been disabled for {$school->name}.");

    expect($school->fresh()->hasManualAttendance())->toBeFalse()
        ->and($otherSchool->fresh()->hasManualAttendance())->toBeTrue()
        ->and(AttendanceSummary::count())->toBe(1);

    $this->actingAs($teacher, 'staff')->get(route('staff.attendance.manual', $school))->assertForbidden();

    $this->actingAs($superAdmin)
        ->post(route('super-admin.schools.manual-attendance', $school), ['enabled' => 1])
        ->assertSessionHas('status', "Manual attendance has been enabled for {$school->name}.");

    // A fresh instance, as a real request loads: the one above still holds
    // the school as it was when that request ran.
    $this->actingAs($teacher->fresh(), 'staff')
        ->get(route('staff.attendance.manual', [$school, 'session' => '2025/2026', 'term' => 'second']))
        ->assertOk()
        ->assertSee('Saved')
        ->assertDontSee('Not recorded');

    expect(AttendanceSummary::sole()->days_present)->toBe(57);
});

test('a School Admin cannot change the switch', function () {
    [$school] = manualAttendanceSchool();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->put(route('settings.update'), [
        'name' => $school->name,
        'timezone' => 'Africa/Lagos',
        'manual_attendance_enabled' => '0',
    ]);

    $this->actingAs($admin)->post(route('super-admin.schools.manual-attendance', $school), ['enabled' => 0]);

    expect($school->fresh()->hasManualAttendance())->toBeTrue();
});

test('the app reads the flag from /me and is refused by the API when disabled', function () {
    [$school, $teacher, $student] = manualAttendanceSchool();
    $token = $teacher->createToken('Test device')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.school.features.manual_attendance', true);

    $this->withToken($token)
        ->getJson('/api/v1/staff/attendance/term-totals?session=2025/2026&term=second')
        ->assertOk()
        ->assertJsonPath('meta.school_days', 60)
        ->assertJsonPath('data.0.student.id', $student->uuid);

    $this->withToken($token)
        ->postJson('/api/v1/staff/attendance/term-totals', [
            ...manualEntry($student, 55, 5),
            'entries' => [$student->uuid => ['days_present' => 55, 'days_absent' => 5]],
        ])
        ->assertOk();

    expect(AttendanceSummary::sole()->days_present)->toBe(55);

    $school->forceFill(['manual_attendance_enabled' => false])->save();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertJsonPath('data.school.features.manual_attendance', false);

    $this->withToken($token)
        ->getJson('/api/v1/staff/attendance/term-totals')
        ->assertForbidden()
        ->assertJsonPath('code', 'feature_disabled')
        ->assertJsonPath('message', 'This feature has been disabled for your school.');

    $this->withToken($token)
        ->postJson('/api/v1/staff/attendance/term-totals', [
            ...manualEntry($student, 10),
            'entries' => [$student->uuid => ['days_present' => 10]],
        ])
        ->assertForbidden();

    expect(AttendanceSummary::sole()->days_present)->toBe(55);
});

test('the School Admin sets school days per term', function () {
    [$school] = manualAttendanceSchool();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

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
    $school->forceFill(['manual_attendance_enabled' => false])->save();

    $attendance = ReportCardData::for($examination, $student->fresh())['attendance'];

    expect($attendance['source'])->toBe('register')
        ->and($attendance['absent'])->toBe(1);
});
