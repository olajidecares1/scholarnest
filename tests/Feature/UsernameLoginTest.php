<?php

use App\Models\User;

test('generateUniqueUsernameFromEmail derives a slug from the email local part', function () {
    expect(User::generateUniqueUsernameFromEmail('Jane.Doe@example.com'))->toBe('janedoe');
});

test('generateUniqueUsernameFromEmail avoids collisions by appending a suffix', function () {
    User::factory()->create(['username' => 'janedoe']);

    expect(User::generateUniqueUsernameFromEmail('jane.doe@another.com'))->toBe('janedoe1');
});

test('registering a new school assigns the admin a unique username', function () {
    $this->post(route('register'), [
        'school_name' => 'Greenfield Academy',
        'email' => 'admin@greenfield.test',
        'phone' => '+234 801 234 5678',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ]);

    $user = User::where('email', 'admin@greenfield.test')->firstOrFail();
    expect($user->username)->toBe('admin');
});
