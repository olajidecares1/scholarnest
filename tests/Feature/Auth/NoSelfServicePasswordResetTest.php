<?php

use App\Models\School;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
});

test('no forgot-password route exists for the student guard', function () {
    foreach (['student.password.request', 'student.password.email', 'student.password.reset', 'student.password.store'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});

test('no forgot-password route exists for the staff guard', function () {
    foreach (['staff.password.request', 'staff.password.email', 'staff.password.reset', 'staff.password.store'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});

test('no forgot-password route exists for the guardian guard', function () {
    foreach (['guardian.password.request', 'guardian.password.email', 'guardian.password.reset', 'guardian.password.store'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});

test('the student login page has no forgot-password link', function () {
    $this->get(route('student.login', [$this->school, $this->school->portal_student_token]))
        ->assertStatus(200)
        ->assertDontSee('Forgot password', false)
        ->assertDontSee('forgot-password', false);
});

test('the staff login page has no forgot-password link', function () {
    $this->get(route('staff.login', [$this->school, $this->school->portal_staff_token]))
        ->assertStatus(200)
        ->assertDontSee('Forgot password', false)
        ->assertDontSee('forgot-password', false);
});

test('the guardian login page has no forgot-password link', function () {
    $this->get(route('guardian.login', [$this->school, $this->school->portal_guardian_token]))
        ->assertStatus(200)
        ->assertDontSee('Forgot password', false)
        ->assertDontSee('forgot-password', false);
});

test('the school admin login page does have a working forgot-password link', function () {
    // The link moved with the page: School Admins now sign in at their own
    // school's portal, so that is where their reset has to be reachable from.
    $this->get(route('portal.admin.login', [$this->school, $this->school->portal_admin_token]))
        ->assertStatus(200)
        ->assertSee('Forgot password')
        ->assertSee(route('password.request'), false);
});
