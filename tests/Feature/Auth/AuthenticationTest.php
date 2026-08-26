<?php

use App\Models\User;

test('the shared login route sends visitors to the public front door', function () {
    // There is no shared sign-in page any more: schools sign in at their own
    // portal and the Super Admin through the hidden dialog on the registration
    // page. The route name survives because Laravel's auth middleware and
    // several fallbacks redirect to it.
    $this->get(route('login'))->assertRedirect(route('register'));
});

test('users can authenticate using their email on the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate using their username on the login screen', function () {
    $user = User::factory()->create(['username' => 'jane_doe']);

    $response = $this->post(route('login'), [
        'login' => 'jane_doe',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('login is throttled after five failed attempts', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest();
});
