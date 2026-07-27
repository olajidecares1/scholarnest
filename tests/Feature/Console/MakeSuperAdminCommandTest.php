<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

test('it creates a super admin account', function () {
    $this->artisan('make:super-admin')
        ->expectsQuestion('Full name', 'Ada Admin')
        ->expectsQuestion('Email address', 'ada@edunest.example')
        ->expectsQuestion('Password', 'SuperSecret123!')
        ->expectsQuestion('Confirm password', 'SuperSecret123!')
        ->assertSuccessful();

    $user = User::where('email', 'ada@edunest.example')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(UserRole::SuperAdmin);
    expect($user->school_id)->toBeNull();
});

test('email must be unique', function () {
    User::factory()->create(['email' => 'taken@edunest.example']);

    $errors = Validator::make(
        ['email' => 'taken@edunest.example'],
        ['email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class]]
    )->errors();

    expect($errors->has('email'))->toBeTrue();
});
