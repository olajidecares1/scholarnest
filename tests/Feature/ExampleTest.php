<?php

use App\Models\User;

it('redirects guests to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard'));
});
