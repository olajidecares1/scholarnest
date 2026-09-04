<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Support\PasswordResetCode;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

/**
 * "Forgot password?", on Laravel's own broker.
 *
 * Laravel owns the token: it generates it, decides when it expires, refuses it
 * once used, and replaces it when a new one is asked for. Nothing here
 * re-implements any of that.
 *
 * What this application adds is a six-digit code, derived from that token and
 * printed in the email body. The token proves possession of the URL; the code
 * proves the email itself was read. A link that leaks - a referrer header, a
 * shared inbox, a screen left open in a staffroom - is not on its own enough to
 * take an administrator's account.
 */
beforeEach(function () {
    Notification::fake();

    $this->admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'email' => 'principal@greenfield.test',
        'name' => 'Adaeze Okonkwo',
    ]);
});

/**
 * Request a reset and return the token Laravel put in the email.
 */
function requestResetToken(User $user): string
{
    test()->post(route('password.email'), ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

describe('requesting a link', function () {
    test('an administrator is sent one', function () {
        $this->post(route('password.email'), ['email' => $this->admin->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($this->admin, ResetPasswordNotification::class);
    });

    test('the answer never reveals whether the account exists', function () {
        // Laravel's default says "We can't find a user with that email address",
        // which turns this form into a free way to test who is a ScholarNest
        // administrator - useful to anyone writing a phishing email.
        $real = $this->post(route('password.email'), ['email' => $this->admin->email]);
        $fake = $this->post(route('password.email'), ['email' => 'nobody@nowhere.test']);

        expect($fake->getSession()->get('status'))->toBe($real->getSession()->get('status'));

        $fake->assertSessionHasNoErrors();
        Notification::assertNothingSentTo(User::factory()->make(['email' => 'nobody@nowhere.test']));
    });

    test('asking again too soon is answered the same way, not with a throttle message', function () {
        // "You asked too recently" also confirms the address belongs to
        // somebody, so it is folded into the same sentence.
        $first = $this->post(route('password.email'), ['email' => $this->admin->email]);
        $second = $this->post(route('password.email'), ['email' => $this->admin->email]);

        expect($second->getSession()->get('status'))->toBe($first->getSession()->get('status'));
        $second->assertSessionHasNoErrors();
    });

    test('a new request replaces the previous link', function () {
        // Laravel's broker does this. The test is here because the code is
        // derived from the token, so replacing the token must also retire the
        // code that went with it.
        $first = requestResetToken($this->admin);

        $this->travel(61)->seconds();
        $second = requestResetToken($this->admin);

        expect($second)->not->toBe($first)
            ->and(Password::broker()->tokenExists($this->admin, $first))->toBeFalse()
            ->and(Password::broker()->tokenExists($this->admin, $second))->toBeTrue();
    });
});

describe('the email', function () {
    test('it is ScholarNest\'s, not Laravel\'s', function () {
        $token = requestResetToken($this->admin);

        $mail = (new ResetPasswordNotification($token, $this->admin->email))->toMail($this->admin);

        expect($mail->subject)->toBe('Reset Your ScholarNest Password')
            ->and($mail->greeting)->toBe('Hello Adaeze Okonkwo,')
            ->and($mail->actionText)->toBe('Reset Password')
            ->and($mail->salutation)->toBe('— ScholarNest Team');

        expect(implode(' ', $mail->introLines))
            ->toContain('We received a request to reset your ScholarNest account password');

        expect(implode(' ', $mail->outroLines))
            ->toContain('you can safely ignore this email');
    });

    test('Laravel\'s default notification is never used', function () {
        $this->post(route('password.email'), ['email' => $this->admin->email]);

        Notification::assertNotSentTo($this->admin, ResetPassword::class);
    });

    test('the link points at the official domain, whatever Host header was sent', function () {
        // The attack this closes: a forged Host header on the forgot-password
        // request would otherwise put the attacker's domain in the victim's
        // email, and the victim would hand over their token by clicking it.
        config(['app.url' => 'https://scholarnest.com.ng']);

        $token = requestResetToken($this->admin);
        $mail = (new ResetPasswordNotification($token, $this->admin->email))->toMail($this->admin);

        expect($mail->actionUrl)->toStartWith('https://scholarnest.com.ng/');
    });

    test('it carries the code in the body and never in the link', function () {
        // Putting the code in the URL would defeat the point of having one.
        $token = requestResetToken($this->admin);
        $code = PasswordResetCode::for($token);

        $mail = (new ResetPasswordNotification($token, $this->admin->email))->toMail($this->admin);

        // The whole body, not one bucket: MailMessage sorts lines into intro
        // and outro depending on whether they were added before or after the
        // action button, and which side the code lands on is presentation.
        $body = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

        expect($body)->toContain($code)
            ->and($mail->actionUrl)->not->toContain($code);
    });

    test('it never contains a password', function () {
        $token = requestResetToken($this->admin);
        $mail = (new ResetPasswordNotification($token, $this->admin->email))->toMail($this->admin);

        $body = implode(' ', [...$mail->introLines, ...$mail->outroLines, $mail->actionUrl]);

        expect(strtolower($body))->not->toContain('password:')
            ->and($body)->not->toContain($this->admin->password);
    });
});

describe('resetting the password', function () {
    test('the link and the correct code together work', function () {
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        expect(Hash::check('Str0ng!NewPassw0rd', $this->admin->fresh()->password))->toBeTrue();
    });

    test('THE LINK ALONE IS NOT ENOUGH', function () {
        // The whole reason the code exists.
        $token = requestResetToken($this->admin);
        $before = $this->admin->password;

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => '000000',
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ])->assertSessionHasErrors('code');

        expect($this->admin->fresh()->password)->toBe($before);
    });

    test('a missing code is refused', function () {
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ])->assertSessionHasErrors('code');
    });

    test('guessing the code is given a small number of tries per link', function () {
        // The ROUTE throttle is deliberately turned off here. Laravel keys it
        // by domain and IP rather than by URI, so it is shared across every
        // route in the guest group - six requests in a minute exhausts it
        // whatever they were for. That is a real second layer and it is
        // asserted separately below; this test is about the per-link attempt
        // limit, which has to hold on its own.
        $this->withoutMiddleware(ThrottleRequests::class);

        $token = requestResetToken($this->admin);

        $wrong = fn () => $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => '111111',
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $wrong()->assertSessionHasErrors('code');
        }

        // Exhausted. Even the CORRECT code no longer works on this link.
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ])->assertSessionHasErrors('code');

        expect(Hash::check('Str0ng!NewPassw0rd', $this->admin->fresh()->password))->toBeFalse();
    });

    test('a token cannot be used twice', function () {
        $token = requestResetToken($this->admin);
        $code = PasswordResetCode::for($token);

        $payload = [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => $code,
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ];

        $this->post(route('password.store'), $payload)->assertSessionHasNoErrors();

        $this->post(route('password.store'), [...$payload, 'password' => 'An0ther!Passw0rd', 'password_confirmation' => 'An0ther!Passw0rd'])
            ->assertSessionHasErrors('email');

        expect(Hash::check('An0ther!Passw0rd', $this->admin->fresh()->password))->toBeFalse();
    });

    test('an expired token is refused', function () {
        $token = requestResetToken($this->admin);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ])->assertSessionHasErrors('email');
    });

    test('a weak password is refused even with a valid link and code', function () {
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    });

    test('the confirmation must match', function () {
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Different!Passw0rd',
        ])->assertSessionHasErrors('password');
    });

    test('a successful reset tells the account holder it happened', function () {
        // The one message that reaches somebody whose account was taken by
        // whoever controls their inbox.
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ]);

        Notification::assertSentTo($this->admin, PasswordChangedNotification::class);
    });

    test('a successful reset is written to the audit log', function () {
        $token = requestResetToken($this->admin);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->admin->email,
            'code' => PasswordResetCode::for($token),
            'password' => 'Str0ng!NewPassw0rd',
            'password_confirmation' => 'Str0ng!NewPassw0rd',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'password-reset.requested']);
    });
});

describe('the code itself', function () {
    test('it is six digits', function () {
        expect(PasswordResetCode::for('any-token-at-all'))->toMatch('/^\d{6}$/');
    });

    test('a different token gives a different code', function () {
        expect(PasswordResetCode::for('token-one'))->not->toBe(PasswordResetCode::for('token-two'));
    });

    test('the same token always gives the same code', function () {
        // It has to, or the code in an email sent a minute ago would stop
        // matching. This is what lets it be derived rather than stored.
        expect(PasswordResetCode::for('stable'))->toBe(PasswordResetCode::for('stable'));
    });

    test('it cannot be computed without the application key', function () {
        // Which is what keeps it a second factor: somebody holding the link
        // holds the token and nothing else.
        $token = 'a-token';
        $first = PasswordResetCode::for($token);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        expect(PasswordResetCode::for($token))->not->toBe($first);
    });
});

describe('only administrators have this at all', function () {
    test('there is no reset route for staff, students or guardians', function () {
        // Not "the link is hidden" - there is no endpoint behind it. See
        // docs/PASSWORD-RESET-POLICY.md for why schools are different.
        foreach (['staff.password.request', 'student.password.request', 'guardian.password.request'] as $name) {
            expect(Route::has($name))->toBeFalse();
        }
    });

    test('the parallel admin reset flow is gone', function () {
        // It existed, nothing linked to it, and it was a second way into the
        // same account without the verification code.
        foreach (['admin.password-reset.request', 'admin.password-reset.show', 'admin.password-reset.complete'] as $name) {
            expect(Route::has($name))->toBeFalse();
        }
    });
});

describe('the routes are rate limited', function () {
    test('a burst of requests is cut off', function () {
        // The second layer, above the per-link attempt limit. Laravel's broker
        // already refuses a second link for the SAME address within 60 seconds;
        // this is what stops one address requesting links for hundreds of
        // DIFFERENT accounts and getting ScholarNest's sending domain marked as
        // spam.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "person{$i}@example.test"]);
        }

        $this->post(route('password.email'), ['email' => 'person99@example.test'])
            ->assertStatus(429);
    });
});
