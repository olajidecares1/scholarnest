<?php

use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PlanSeeder;

/**
 * The Basic plan's academic system.
 *
 * Basic is not a cut-down academic engine. It runs the same students, teachers,
 * attendance, marks, grading and result generation as Standard and Exclusive,
 * the difference is which premium modules sit on top, not the quality of the
 * school's academic records.
 *
 * These tests pin both halves of that: the core academic system must be
 * REACHABLE on Basic, and the premium modules must not be.
 */
function basicSchoolWithTeacher(PlanKey $plan = PlanKey::Basic): array
{
    $school = School::factory()->create();
    activateSchool($school, $plan);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    return [$school, $teacher];
}

// -----------------------------------------------------------------------------
// Unlimited teachers
// -----------------------------------------------------------------------------

test('the Basic plan does not cap teacher accounts', function () {
    (new PlanSeeder)->run();

    // Basic is sold per STUDENT. Capping teachers on top of that charged a
    // school twice for the same licence.
    expect(Plan::where('key', PlanKey::Basic)->first()->max_teachers)->toBeNull();
});

test('no seeded plan caps teacher accounts', function () {
    (new PlanSeeder)->run();

    expect(Plan::whereNotNull('max_teachers')->count())->toBe(0);
});

test('a Basic school can add far more teachers than the old cap allowed', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    // The cap used to be 10, so this would have failed at the eleventh.
    Staff::factory()->count(25)->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    expect($school->teacherAccountLimit())->toBeNull();

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)
        ->post(route('staff.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Okoro',
            'email' => 'ada.okoro@example.com',
            'staff_number' => 'STF-999',
            'gender' => Gender::Female->value,
            'role' => StaffRole::Teacher->value,
        ])
        ->assertSessionHasNoErrors();

    expect($school->staff()->where('role', StaffRole::Teacher)->count())->toBe(26);
});

// -----------------------------------------------------------------------------
// The core academic system is reachable on Basic
// -----------------------------------------------------------------------------

test('a Basic teacher reaches the staff dashboard rather than the locked page', function () {
    [$school, $teacher] = basicSchoolWithTeacher();

    // Basic schools used to be locked out of this portal entirely, which left
    // the School Admin personally typing in every teacher's marks.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk();
});

test('a Basic teacher reaches attendance, marks entry and report cards', function (string $route) {
    [$school, $teacher] = basicSchoolWithTeacher();

    $this->actingAs($teacher, 'staff')
        ->get(route($route, $school))
        ->assertOk();
})->with([
    'attendance' => 'staff.attendance.index',
    'marks register' => 'staff.exams.index',
    'report cards' => 'staff.results.index',
]);

test('a school with no active subscription still cannot reach the staff portal', function () {
    $school = School::factory()->create();

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    // Opening the portal to every PLAN is not the same as opening it to
    // schools that have not paid.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertRedirect(route('staff.locked', $school));
});

// -----------------------------------------------------------------------------
// The premium modules are not
// -----------------------------------------------------------------------------

test('a Basic teacher cannot reach CBT or ID cards in the staff portal', function (string $route) {
    [$school, $teacher] = basicSchoolWithTeacher();

    $this->actingAs($teacher, 'staff')
        ->get(route($route, $school))
        ->assertForbidden();
})->with([
    'cbt' => 'staff.cbt.tests.index',
    'id card' => 'staff.id-card.show',
]);

test('a Standard teacher can reach CBT and ID cards', function (string $route) {
    [$school, $teacher] = basicSchoolWithTeacher(PlanKey::Standard);

    // The gate has to distinguish plans, not simply refuse everyone, which is
    // what a gate reading the wrong auth guard would have done.
    $this->actingAs($teacher, 'staff')
        ->get(route($route, $school))
        ->assertOk();
})->with([
    'cbt' => 'staff.cbt.tests.index',
    'id card' => 'staff.id-card.show',
]);

test('the Basic staff dashboard does not link to CBT', function () {
    [$school, $teacher] = basicSchoolWithTeacher();

    // A link that 403s is worse than no link.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertDontSee('CBT Management');
});

test('the Standard staff dashboard does link to CBT', function () {
    [$school, $teacher] = basicSchoolWithTeacher(PlanKey::Standard);

    // The link, not the wording: the mobile menu labels this "CBT", and what
    // the plan rule is about is whether a Standard teacher can reach it.
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee(route('staff.cbt.tests.index', $school), false);
});

// -----------------------------------------------------------------------------
// No parent portal on Basic, but not no parent result access
// -----------------------------------------------------------------------------

test('a Basic guardian gets no parent portal', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    $student = Student::factory()->create(['school_id' => $school->id]);
    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'must_change_password' => false]);
    $guardian->students()->attach($student->id, ['relationship' => 'Mother']);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $school))
        ->assertRedirect(route('guardian.locked', $school));
});

test('a Basic student gets no student portal', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    $student = Student::factory()->create(['school_id' => $school->id, 'must_change_password' => false]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $school))
        ->assertRedirect(route('student.locked', $school));
});

test('a Basic parent still reaches a result through the token flow', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    // No portal, no account, no dashboard, and the result is still reachable.
    // "No Parent Portal" is not "no parent result access".
    $this->get(route('check-result.show', $school))
        ->assertOk()
        ->assertSee('School ID / Admission Number');
});

// -----------------------------------------------------------------------------
// The Basic school's portal page: which doors it offers
// -----------------------------------------------------------------------------

test('a Basic school portal page offers the teacher login', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    // Opening the staff portal is only half of it, a teacher also has to be
    // able to find the way in, and a Basic school has no website to link from.
    $this->get('/'.$school->portal_key)
        ->assertOk()
        ->assertSee('Staff / Teacher')
        ->assertSee('School Admin');
});

test('a Basic school portal page does not offer student or parent logins', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    // Those two portals are Standard and Exclusive only, so on Basic they
    // would open onto the locked page.
    $this->get('/'.$school->portal_key)
        ->assertOk()
        ->assertDontSee('Parent / Guardian')
        ->assertDontSee('Results, assignments');
});

test('a Basic school portal page sends parents to the result token instead', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    // No Parent Portal is not no parent result access.
    $this->get('/'.$school->portal_key)
        ->assertOk()
        ->assertSee('Check Result')
        // The school's own result address, which is what a Basic school hands
        // to parents.
        ->assertSee('/'.$school->fresh()->result_link_slug.'/result');
});

test('a Standard school portal page still offers all four logins', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);

    // Asked for through the shared portal route rather than the root slug: a
    // Standard school is redirected off the root to its own public website,
    // which is a different behaviour and not what this test is about.
    $this->get(route('portal.index', $school))
        ->assertOk()
        ->assertSee('School Admin')
        ->assertSee('Staff / Teacher')
        ->assertSee('Student')
        ->assertSee('Parent / Guardian')
        ->assertDontSee('Check Result');
});

test('the Basic portal page is the same page, reached through the shared portal route', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    $this->get(route('portal.index', $school))
        ->assertOk()
        ->assertSee('Staff / Teacher')
        ->assertSee('Check Result')
        ->assertDontSee('Parent / Guardian');
});
