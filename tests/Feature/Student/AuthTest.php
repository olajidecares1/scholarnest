<?php

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;

function studentPortalSchool(PlanKey $planKey = PlanKey::Standard, SubscriptionStatus $status = SubscriptionStatus::Active): School
{
    $school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => $planKey], Plan::factory()->make(['key' => $planKey])->toArray());
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => $status]);

    return $school;
}

test('a student can log in with their admission number and password', function () {
    $school = studentPortalSchool();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->post(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]), [
        'login' => $student->admission_number,
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard', $school));

    $this->assertAuthenticatedAs($student, 'student');
});

test('a student can log in with their email', function () {
    $school = studentPortalSchool();
    $student = Student::factory()->create(['school_id' => $school->id, 'email' => 'daniel@example.com']);

    $this->post(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]), [
        'login' => 'daniel@example.com',
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard', $school));

    $this->assertAuthenticatedAs($student, 'student');
});

test('a wrong password is rejected', function () {
    $school = studentPortalSchool();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->post(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]), [
        'login' => $student->admission_number,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('student');
});

test('two schools can each have a student with the same admission number without collision', function () {
    $schoolA = studentPortalSchool();
    $schoolB = studentPortalSchool();

    $studentA = Student::factory()->create(['school_id' => $schoolA->id, 'admission_number' => 'STU-0001']);
    Student::factory()->create(['school_id' => $schoolB->id, 'admission_number' => 'STU-0001']);

    $this->post(route('student.login', ['school' => $schoolA, 'token' => $schoolA->portal_student_token]), [
        'login' => 'STU-0001',
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard', $schoolA));

    $this->assertAuthenticatedAs($studentA, 'student');
});

test('failed attempts against a student at one school do not lock out an identically-numbered student at another school', function () {
    $schoolA = studentPortalSchool();
    $schoolB = studentPortalSchool();

    Student::factory()->create(['school_id' => $schoolA->id, 'admission_number' => 'STU-0001']);
    $studentB = Student::factory()->create(['school_id' => $schoolB->id, 'admission_number' => 'STU-0001']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('student.login', ['school' => $schoolA, 'token' => $schoolA->portal_student_token]), [
            'login' => 'STU-0001',
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('student.login', ['school' => $schoolA, 'token' => $schoolA->portal_student_token]), [
        'login' => 'STU-0001',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->post(route('student.login', ['school' => $schoolB, 'token' => $schoolB->portal_student_token]), [
        'login' => 'STU-0001',
        'password' => 'password',
    ])->assertRedirect(route('student.dashboard', $schoolB));

    $this->assertAuthenticatedAs($studentB, 'student');
});

test('an inactive student cannot access the portal', function () {
    $school = studentPortalSchool();
    $student = Student::factory()->create(['school_id' => $school->id, 'is_active' => false]);

    $this->post(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]), [
        'login' => $student->admission_number,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest('student');
});

test('a student from a school without a qualifying plan is redirected to the locked page', function () {
    $school = studentPortalSchool(PlanKey::Basic);
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $school))
        ->assertRedirect(route('student.locked', $school));
});

test('a student from a school with no active subscription is redirected to the locked page', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $school))
        ->assertRedirect(route('student.locked', $school));
});

test('a student from a school on the exclusive plan can access the portal', function () {
    $school = studentPortalSchool(PlanKey::Exclusive);
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $school))
        ->assertStatus(200);
});

test('a student can log out', function () {
    $school = studentPortalSchool();
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($student, 'student')
        ->post(route('student.logout', $school))
        ->assertRedirect(route('student.login', ['school' => $school, 'token' => $school->portal_student_token]));

    $this->assertGuest('student');
});
