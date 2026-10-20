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
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ReportCardData;

/**
 * Manual term attendance: a teacher types in each pupil's days present,
 * absent, late and excused for the term.
 *
 * Optional, per school, off by default, and on every plan. It sits beside the
 * daily register and QR check-in, so nothing here may change what those
 * record.
 */
const MANUAL_SESSION = '2025/2026';

/**
 * @return array{0: School, 1: User, 2: Staff, 3: Student}
 */
function manualAttendanceSchool(PlanKey $plan = PlanKey::Basic, bool $enabled = true): array
{
    $school = School::factory()->create(['current_session' => MANUAL_SESSION]);
    activateSchool($school, $plan);

    if ($enabled) {
        $school->update(['manual_attendance_enabled' => true]);
    }

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

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

    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'is_active' => true]);

    return [$school->fresh(), $admin, $teacher, $student];
}

function firstTermDates(School $school, ?int $schoolDays = 60): AcademicTerm
{
    return AcademicTerm::create([
        'school_id' => $school->id,
        'session' => MANUAL_SESSION,
        'term' => ExamTerm::First,
        'starts_on' => '2025-09-08',
        'ends_on' => '2025-12-12',
        'school_days' => $schoolDays,
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function termPayload(array $rows, string $class = 'Primary 6', ExamTerm $term = ExamTerm::First): array
{
    return ['class_name' => $class, 'session' => MANUAL_SESSION, 'term' => $term->value, 'rows' => $rows];
}

function counts(int $present, int $absent = 0, int $late = 0, int $excused = 0): array
{
    return ['days_present' => $present, 'days_absent' => $absent, 'days_late' => $late, 'days_excused' => $excused];
}

// -----------------------------------------------------------------------------
// Optional, per school, every plan
// -----------------------------------------------------------------------------

test('it is off by default, and a teacher keeps the daily register', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool(enabled: false);

    expect($school->usesManualAttendance())->toBeFalse();

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.term.index', $school))
        ->assertOk()
        ->assertSee("Term attendance entry isn't turned on at your school", false);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50)]))
        ->assertForbidden();

    expect(AttendanceSummary::count())->toBe(0);
});

test('each school turns it on or off for itself, on every plan', function (PlanKey $plan) {
    [$school, $admin] = manualAttendanceSchool($plan, enabled: false);

    $this->actingAs($admin)
        ->put(route('attendance.term.settings'), ['manual_attendance_enabled' => '1', 'manual_attendance_teacher_edits' => '1'])
        ->assertSessionHasNoErrors();

    expect($school->fresh()->usesManualAttendance())->toBeTrue();

    $this->actingAs($admin)->get(route('attendance.term.index'))->assertOk()->assertSee('Save First Term', false);

    $this->actingAs($admin)->put(route('attendance.term.settings'), []);

    expect($school->fresh()->usesManualAttendance())->toBeFalse();
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a class teacher can enter totals on every plan', function (PlanKey $plan) {
    [$school, , $teacher, $student] = manualAttendanceSchool($plan);

    $this->actingAs($teacher, 'staff')->get(route('staff.attendance.term.index', $school))->assertOk()->assertSee($student->fullName());

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50, 5, 3, 2)]))
        ->assertSessionHasNoErrors();

    $summary = AttendanceSummary::sole();

    expect($summary->school_id)->toBe($school->id)
        ->and($summary->student_id)->toBe($student->id)
        ->and($summary->term)->toBe(ExamTerm::First)
        ->and($summary->class_name)->toBe('Primary 6')
        ->and($summary->marked_by_staff_id)->toBe($teacher->id)
        ->and($summary->totalDays())->toBe(60)
        ->and($summary->percent())->toBe(88);
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

// -----------------------------------------------------------------------------
// Entering and totalling
// -----------------------------------------------------------------------------

test('saving again updates the same row rather than adding one', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50)]));
    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(55, 5)]));

    expect(AttendanceSummary::count())->toBe(1)
        ->and(AttendanceSummary::sole()->counts())->toBe(counts(55, 5));
});

test('blank rows are skipped and a blank count beside filled ones is zero', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();
    $classmate = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'is_active' => true]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([
        $student->id => ['days_present' => 40, 'days_absent' => '', 'days_late' => null, 'days_excused' => ''],
        $classmate->id => ['days_present' => '', 'days_absent' => '', 'days_late' => '', 'days_excused' => ''],
    ]))->assertSessionHasNoErrors();

    expect(AttendanceSummary::count())->toBe(1)
        ->and(AttendanceSummary::sole()->counts())->toBe(counts(40));
});

test('counts must be whole numbers from 0 to 366', function (mixed $bad) {
    [$school, , $teacher, $student] = manualAttendanceSchool();

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([$student->id => ['days_present' => $bad]]))
        ->assertSessionHasErrors("rows.{$student->id}.days_present");

    expect(AttendanceSummary::count())->toBe(0);
})->with(['negative' => -1, 'too many' => 400, 'fraction' => 2.5, 'text' => 'ten']);

test('a teacher can only enter totals for their own class', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();
    $elsewhere = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 5', 'is_active' => true]);

    // Their class, with a pupil from another class slipped into the rows.
    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([
        $student->id => counts(50),
        $elsewhere->id => counts(10),
    ]));

    // Another class outright.
    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([$elsewhere->id => counts(10)], 'Primary 5'))
        ->assertSessionHasErrors('class_name');

    expect(AttendanceSummary::pluck('student_id')->all())->toBe([$student->id]);
});

test('totals are checked against the term\'s school days and differences flagged, not refused', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();
    firstTermDates($school, 60);

    $over = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'is_active' => true]);
    $exact = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'is_active' => true]);

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([
            $student->id => counts(50, 5, 2, 1), // 58: two short
            $over->id => counts(60, 3),          // 63: three over
            $exact->id => counts(57, 1, 1, 1),   // 60
        ]))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '2 do not match'));

    expect(AttendanceSummary::count())->toBe(3);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.term.index', [$school, 'session' => MANUAL_SESSION, 'term' => 'first']))
        ->assertOk()
        ->assertSee('2 days short of the 60 school days this term')
        ->assertSee('3 days more than the 60 school days this term');
});

test('without a number set, school days are the weekdays of the term', function () {
    $school = School::factory()->create();

    $term = AcademicTerm::create([
        'school_id' => $school->id, 'session' => MANUAL_SESSION, 'term' => ExamTerm::First,
        // Monday 8 September to Friday 19 September: two school weeks.
        'starts_on' => '2025-09-08', 'ends_on' => '2025-09-19',
    ]);

    expect($term->schoolDays())->toBe(10)
        ->and($term->hasConfiguredSchoolDays())->toBeFalse();

    $term->update(['school_days' => 9]);

    expect($term->fresh()->schoolDays())->toBe(9);
});

test('the School Admin sets school days with the term dates', function () {
    [$school, $admin] = manualAttendanceSchool();

    $this->actingAs($admin)->post(route('academics.terms.store'), [
        'session' => MANUAL_SESSION, 'term' => 'first', 'starts_on' => '2025-09-08', 'ends_on' => '2025-12-12', 'school_days' => 62,
    ])->assertSessionHasNoErrors();

    expect(AcademicTerm::sole()->school_days)->toBe(62);

    // More school days than the term has days is a typo, not a calendar.
    $this->actingAs($admin)->post(route('academics.terms.store'), [
        'session' => MANUAL_SESSION, 'term' => 'second', 'starts_on' => '2026-01-05', 'ends_on' => '2026-01-16', 'school_days' => 40,
    ])->assertSessionHasErrors('school_days');
});

// -----------------------------------------------------------------------------
// Corrections and permissions
// -----------------------------------------------------------------------------

test('a teacher corrects saved totals when the school allows it', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();

    expect($school->allowsTeacherAttendanceCorrections())->toBeTrue();

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50)]));
    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(48, 2)]));

    expect(AttendanceSummary::sole()->counts())->toBe(counts(48, 2));
});

test('when corrections are kept for the School Admin, a teacher cannot overwrite saved totals but the admin can', function () {
    [$school, $admin, $teacher, $student] = manualAttendanceSchool();
    $school->update(['manual_attendance_teacher_edits' => false]);
    $newcomer = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'is_active' => true]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50)]));

    $this->actingAs($teacher, 'staff')
        ->post(route('staff.attendance.term.store', $school), termPayload([
            $student->id => counts(10),
            $newcomer->id => counts(30),
        ]))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 already had totals and were not changed'));

    expect(AttendanceSummary::where('student_id', $student->id)->sole()->counts())->toBe(counts(50))
        // A pupil with no totals yet can still be filled in.
        ->and(AttendanceSummary::where('student_id', $newcomer->id)->sole()->counts())->toBe(counts(30));

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.term.index', $school))
        ->assertSee('School Admin corrects');

    $this->actingAs($admin)
        ->post(route('attendance.term.store'), termPayload([$student->id => counts(45, 5)]))
        ->assertSessionHasNoErrors();

    $summary = AttendanceSummary::where('student_id', $student->id)->sole();

    expect($summary->counts())->toBe(counts(45, 5))
        ->and($summary->marked_by)->toBe($admin->id);
});

test('a School Admin cannot write another school\'s pupils', function () {
    [$school, $admin] = manualAttendanceSchool();
    [, , , $otherStudent] = manualAttendanceSchool();

    $this->actingAs($admin)->post(route('attendance.term.store'), termPayload([$otherStudent->id => counts(50)]));

    expect(AttendanceSummary::count())->toBe(0);
});

// -----------------------------------------------------------------------------
// Living beside the daily register and QR check-in
// -----------------------------------------------------------------------------

test('entering totals never touches the daily register, and the register keeps working', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();
    $school->update(['check_in_enabled' => true, 'student_attendance_mode' => 'qr', 'latitude' => 6.5, 'longitude' => 3.3]);

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.store', $school), [
        'class_name' => 'Primary 6',
        'date' => now()->toDateString(),
        'records' => [$student->id => AttendanceStatus::Late->value],
    ])->assertSessionHasNoErrors();

    $this->actingAs($teacher, 'staff')->post(route('staff.attendance.term.store', $school), termPayload([$student->id => counts(50)]));

    expect(AttendanceRecord::count())->toBe(1)
        ->and(AttendanceRecord::sole()->status)->toBe(AttendanceStatus::Late)
        ->and(AttendanceSummary::count())->toBe(1);
});

test('the entry page offers the daily register\'s own tallies to start from', function () {
    [$school, , $teacher, $student] = manualAttendanceSchool();
    firstTermDates($school);

    foreach (['2025-09-08' => AttendanceStatus::Present, '2025-09-09' => AttendanceStatus::Absent, '2025-09-10' => AttendanceStatus::Present] as $date => $status) {
        AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $student->id, 'class_name' => 'Primary 6', 'date' => $date, 'status' => $status]);
    }

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.attendance.term.index', [$school, 'session' => MANUAL_SESSION, 'term' => 'first']))
        ->assertOk()
        ->assertSee('Copy the daily register (3 days)', false);
});

// -----------------------------------------------------------------------------
// Report cards
// -----------------------------------------------------------------------------

test('report cards use the manual totals when the school uses them', function () {
    [$school, , , $student] = manualAttendanceSchool();
    firstTermDates($school, 60);

    AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $student->id, 'class_name' => 'Primary 6', 'date' => '2025-09-08', 'status' => AttendanceStatus::Absent]);
    AttendanceSummary::create(['school_id' => $school->id, 'student_id' => $student->id, 'session' => MANUAL_SESSION, 'term' => ExamTerm::First] + counts(54, 3, 2, 1));

    $exam = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'session' => MANUAL_SESSION, 'term' => ExamTerm::First]);

    expect(ReportCardData::for($exam, $student)['attendance'])->toMatchArray([
        'present' => 54, 'absent' => 3, 'late' => 2, 'excused' => 1, 'total' => 60, 'percent' => 93, 'school_days' => 60, 'source' => 'manual',
    ]);

    // Turned off: the card counts the register again, and the totals are kept.
    $school->update(['manual_attendance_enabled' => false]);

    expect(ReportCardData::for($exam->fresh(), $student->fresh())['attendance'])->toMatchArray([
        'present' => 0, 'absent' => 1, 'total' => 1, 'source' => 'register',
    ])->and(AttendanceSummary::count())->toBe(1);
});

test('a pupil without manual totals falls back to the register on the card', function () {
    [$school, , , $student] = manualAttendanceSchool();
    firstTermDates($school);

    AttendanceRecord::create(['school_id' => $school->id, 'student_id' => $student->id, 'class_name' => 'Primary 6', 'date' => '2025-09-08', 'status' => AttendanceStatus::Present]);

    $exam = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'session' => MANUAL_SESSION, 'term' => ExamTerm::First]);

    expect(ReportCardData::for($exam, $student)['attendance'])->toMatchArray(['present' => 1, 'total' => 1, 'source' => 'register']);
});

test('manual totals reach the card even before term dates are set', function () {
    [$school, , , $student] = manualAttendanceSchool();

    AttendanceSummary::create(['school_id' => $school->id, 'student_id' => $student->id, 'session' => MANUAL_SESSION, 'term' => ExamTerm::Second] + counts(40, 2));

    $exam = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'Primary 6', 'session' => MANUAL_SESSION, 'term' => ExamTerm::Second]);

    expect(ReportCardData::for($exam, $student)['attendance'])->toMatchArray(['present' => 40, 'absent' => 2, 'total' => 42, 'school_days' => null]);
});

// -----------------------------------------------------------------------------
// Pupils and parents
// -----------------------------------------------------------------------------

test('a pupil sees their term totals on their attendance page', function () {
    [$school, , , $student] = manualAttendanceSchool(PlanKey::Standard);
    AttendanceSummary::create(['school_id' => $school->id, 'student_id' => $student->id, 'session' => MANUAL_SESSION, 'term' => ExamTerm::First] + counts(54, 3, 2, 1));

    $student->update(['must_change_password' => false]);

    $this->actingAs($student, 'student')
        ->get(route('student.attendance.index', $school))
        ->assertOk()
        ->assertSee('Term Attendance')
        ->assertSee('54 Present');
});
