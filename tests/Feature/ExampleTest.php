<?php

use App\Models\User;

it('shows guests the registration page at the homepage', function () {
    // "/" answers 200 with the registration page, not a redirect: the
    // homepage search engines index (see routes/public.php).
    $this->get('/')
        ->assertOk()
        ->assertViewHas('isHome', true);
});

it('redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('dashboard'));
});
