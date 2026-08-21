<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sign-in rate limiting.
 *
 * Two independent limits protect the login form, and they defend against two
 * genuinely different attacks:
 *
 *   per credentials  5 failures / 15 min for one email from one IP.
 *                    Stops someone guessing ONE person's password.
 *
 *   per IP           30 failures / 15 min for one IP against any account.
 *                    Stops password spraying: trying one common password
 *                    against MANY accounts. Every attempt uses a different
 *                    email, so the per-credential limit never fires.
 */
beforeEach(function () {
    RateLimiter::clear('login-ip|127.0.0.1');
});

test('a single account is locked after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // The sixth attempt is refused even though the password is now correct.
    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('seconds');
    $this->assertGuest();
});

test('password spraying across many accounts from one address is blocked', function () {
    // 30 different accounts, one guess each. Every per-credential counter sits
    // at 1, so without the per-IP limit this would never be stopped.
    foreach (range(1, 30) as $i) {
        $this->post(route('login'), [
            'login' => "victim{$i}@example.test",
            'password' => 'Password123!',
        ]);
    }

    // A 31st guess at yet another untouched account must now be refused.
    $response = $this->post(route('login'), [
        'login' => 'victim31@example.test',
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('seconds');
});

test('the per address counter is not reset by signing in successfully', function () {
    // Otherwise an attacker could spray, then sign in to an account they
    // genuinely own, and wipe their own counter back to zero at will.
    foreach (range(1, 5) as $i) {
        $this->post(route('login'), [
            'login' => "victim{$i}@example.test",
            'password' => 'Password123!',
        ]);
    }

    expect(RateLimiter::attempts('login-ip|127.0.0.1'))->toBe(5);

    $user = User::factory()->create();

    $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    // Still 5. The successful sign-in cleared only that user's own counter.
    expect(RateLimiter::attempts('login-ip|127.0.0.1'))->toBe(5);
});

test('a genuine user is unaffected by a handful of mistyped passwords', function () {
    // The per-IP limit must be generous enough that a school sharing one
    // internet connection is not locked out by ordinary Monday-morning typos.
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
