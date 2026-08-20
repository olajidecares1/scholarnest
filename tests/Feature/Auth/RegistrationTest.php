<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new schools can register', function () {
    $response = $this->post(route('register'), [
        'school_name' => 'Greenfield Academy',
        'email' => 'admin@greenfield.example',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $school = School::where('name', 'Greenfield Academy')->first();
    expect($school)->not->toBeNull();

    $user = User::where('email', 'admin@greenfield.example')->first();
    expect($user->role)->toBe(UserRole::SchoolAdmin);
    expect($user->school_id)->toBe($school->id);
});

test('registration requires acceptance of terms', function () {
    $response = $this->post(route('register'), [
        'school_name' => 'Greenfield Academy',
        'email' => 'admin@greenfield.example',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('terms');
    $this->assertGuest();
});
