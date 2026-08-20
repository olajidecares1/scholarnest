<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $this->assertTrue(Hash::check('NewPassword@123', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect(route('profile.edit'));
});
