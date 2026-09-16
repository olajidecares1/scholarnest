<?php

use App\Enums\UserRole;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\EmailDelivery;
use App\Models\School;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\Auth\PasswordResetCodes;
use App\Services\Mail\MailReadiness;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * "Forgot password?" for the AkademicNest Team (Super Admin) and School Admins.
 *
 *   1. Enter email     -> a 6-digit code is emailed
 *   2. Enter the code  -> a wrong code is refused, password fields stay hidden
 *   3. New password    -> only after the code is verified
 *
 * Every step is checked on the server. See PasswordResetController.
 */
beforeEach(function () {
    Notification::fake();

    $this->school = School::factory()->create(['name' => 'Greenfield College']);

    $this->schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
        'email' => 'principal@greenfield.test',
        'name' => 'Adaeze Okonkwo',
        'password' => Hash::make('Old!Passw0rd'),
    ]);

    $this->superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'email' => 'team@akademicanest.test',
        'name' => 'Grace Adeyemi',
        'password' => Hash::make('Old!Passw0rd'),
    ]);
});

/**
 * Ask for a code and return the one that was emailed.
 */
function requestResetCode(User $user): string
{
    test()->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

    $code = null;

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

/**
 * A code that is certainly not the right one.
 */
function wrongCode(string $right): string
{
    return $right === '111111' ? '222222' : '111111';
}

dataset('administrators', [
    'School Admin' => ['schoolAdmin'],
    'AkademicNest Super Admin' => ['superAdmin'],
]);

describe('the complete reset, start to finish', function () {
    test('works for every administrator', function (string $who) {
        $user = $this->{$who};

        // Step 1: the email field, then a code sent to the account.
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('name="code"', false)
            ->assertDontSee('name="password"', false);

        $code = requestResetCode($user);

        expect($code)->toMatch('/^\d{6}$/');

        // Step 2: the code field appears, the password fields do not.
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertSee('Check Your Email')
            ->assertDontSee('name="password"', false);

        // A wrong code: a clear error, and still no password fields.
        $this->post(route('password.verify'), ['code' => wrongCode($code)])
            ->assertSessionHasErrors(['code' => PasswordResetController::INVALID_CODE]);

        $this->get(route('password.request'))
            ->assertSee(PasswordResetController::INVALID_CODE)
            ->assertSee('name="code"', false)
            ->assertDontSee('name="password"', false);

        // The same wrong code cannot set a password either, whatever is posted.
        $this->post(route('password.store'), [
            'password' => 'Brand!New2Pass',
            'password_confirmation' => 'Brand!New2Pass',
        ])->assertSessionHasErrors('email');

        expect(Hash::check('Old!Passw0rd', $user->fresh()->password))->toBeTrue();

        // Ask again: the reset had to start over after that refused attempt.
        $code = requestResetCode($user->fresh());

        // The right code: straight on to the password fields.
        $this->post(route('password.verify'), ['code' => $code])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('name="code"', false);

        // Step 3: the new password.
        $this->post(route('password.store'), [
            'password' => 'Brand!New2Pass',
            'password_confirmation' => 'Brand!New2Pass',
        ])->assertSessionHasNoErrors();

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Password Reset')
            ->assertSee('Go to Sign In');

        // The new password works and the old one no longer does.
        $fresh = $user->fresh();

        expect(Hash::check('Brand!New2Pass', $fresh->password))->toBeTrue()
            ->and(Hash::check('Old!Passw0rd', $fresh->password))->toBeFalse()
            ->and(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'Brand!New2Pass']))->toBeTrue()
            ->and(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'Old!Passw0rd']))->toBeFalse();

        // The same code cannot be used again.
        $this->withSession(['password_reset.email' => $user->email])
            ->post(route('password.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => PasswordResetController::INVALID_CODE]);
    })->with('administrators');

    test('a used code is rejected even in the same session', function (string $who) {
        $user = $this->{$who};
        $code = requestResetCode($user);

        $this->post(route('password.verify'), ['code' => $code])->assertSessionHasNoErrors();
        $this->post(route('password.store'), [
            'password' => 'Brand!New2Pass',
            'password_confirmation' => 'Brand!New2Pass',
        ])->assertSessionHasNoErrors();

        // Start again with the same address, and replay the old code.
        $this->post(route('password.restart'));
        $this->withSession(['password_reset.email' => $user->email])
            ->post(route('password.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => PasswordResetController::INVALID_CODE]);

        expect(Hash::check('Brand!New2Pass', $user->fresh()->password))->toBeTrue();
    })->with('administrators');

    test('after a reset, each lands on its own sign-in page', function () {
        $code = requestResetCode($this->superAdmin);
        $this->post(route('password.verify'), ['code' => $code]);
        $this->post(route('password.store'), ['password' => 'Brand!New2Pass', 'password_confirmation' => 'Brand!New2Pass']);

        $this->get(route('password.request'))->assertSee(route('super-admin.login'), false);

        $this->post(route('password.restart'));

        $code = requestResetCode($this->schoolAdmin);
        $this->post(route('password.verify'), ['code' => $code]);
        $this->post(route('password.store'), ['password' => 'Brand!New2Pass', 'password_confirmation' => 'Brand!New2Pass']);

        $this->get(route('password.request'))->assertSee($this->school->portalLoginUrl('web'), false);
    });
});

describe('the email', function () {
    test('carries the code, and never a password or a link to reset', function () {
        $code = requestResetCode($this->schoolAdmin);

        $mail = (new ResetPasswordNotification($code))->toMail($this->schoolAdmin);
        $body = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        expect($mail->subject)->toBe('Your AkademicNest Password Reset Code')
            ->and($body)->toContain($code)
            ->and($body)->toContain('15 minutes')
            ->and($body)->not->toContain('Old!Passw0rd')
            ->and($mail->actionUrl)->toBeNull();
    });

    test('is recorded as delivered', function () {
        requestResetCode($this->schoolAdmin);

        expect(EmailDelivery::where('kind', 'password-reset-code')->where('recipient', $this->schoolAdmin->email)->value('status'))
            ->toBe(EmailDelivery::SENT);
    });

    test('is not claimed as sent when mail is not configured, and no code is left behind', function () {
        // What a production server with MAIL_MAILER=log reports.
        $this->mock(MailReadiness::class, fn ($mock) => $mock->shouldReceive('problem')->andReturn('Email is not set up on this server.'));

        $this->post(route('password.email'), ['email' => $this->schoolAdmin->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();

        expect(DB::table('password_reset_tokens')->count())->toBe(0)
            ->and(EmailDelivery::where('kind', 'password-reset-code')->value('status'))->toBe(EmailDelivery::FAILED);
    });
});

describe('security', function () {
    test('the codes are stored hashed, never in readable form', function () {
        $code = requestResetCode($this->schoolAdmin);

        $row = DB::table('password_reset_tokens')->first();

        expect($row->token)->not->toBe($code)
            ->and(Hash::check($code, $row->token))->toBeTrue();
    });

    test('an address with no account is told so on the email step, and nothing is sent', function () {
        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => PasswordResetController::NO_ACCOUNT]);

        $this->get(route('password.request'))
            ->assertSee('reset-bounce-twice', false)
            ->assertSee('name="email"', false)
            ->assertDontSee('name="code"', false);

        Notification::assertNothingSent();
    });

    test('an address with an account gets a short note saying where the code went', function () {
        $this->post(route('password.email'), ['email' => $this->schoolAdmin->email])
            ->assertSessionHas('status', "Code sent to {$this->schoolAdmin->email}. Check your inbox or spam folder.");

        $this->get(route('password.request'))
            ->assertSee('<small class="text-green-700">Code sent to', false)
            ->assertSee('name="code"', false);
    });

    test('sends that failed do not use up the hourly limit', function () {
        $this->mock(MailReadiness::class, fn ($mock) => $mock->shouldReceive('problem')->andReturn('Email is not set up on this server.'));

        for ($i = 0; $i < 6; $i++) {
            $this->withoutMiddleware(ThrottleRequests::class)
                ->post(route('password.email'), ['email' => $this->schoolAdmin->email]);
        }

        $this->forgetMock(MailReadiness::class);

        requestResetCode($this->schoolAdmin);
    });

    test('staff, students and parents cannot use it', function () {
        foreach (['staff.password.request', 'student.password.request', 'guardian.password.request'] as $name) {
            expect(Route::has($name))->toBeFalse();
        }

        $deactivated = User::factory()->create(['role' => UserRole::SchoolAdmin, 'is_active' => false]);

        $this->post(route('password.email'), ['email' => $deactivated->email]);

        Notification::assertNothingSent();
    });

    test('a new request replaces the earlier code', function () {
        $first = requestResetCode($this->schoolAdmin);

        $this->travel(2)->minutes();
        Notification::fake();

        $second = requestResetCode($this->schoolAdmin);

        if ($first === $second) {
            $this->markTestSkipped('The two random codes happened to match.');
        }

        $this->post(route('password.verify'), ['code' => $first])
            ->assertSessionHasErrors(['code' => PasswordResetController::INVALID_CODE]);

        $this->post(route('password.verify'), ['code' => $second])->assertSessionHasNoErrors();
    });

    test('codes expire', function () {
        $code = requestResetCode($this->schoolAdmin);

        $this->travel(PasswordResetCodes::EXPIRES_MINUTES + 1)->minutes();

        $this->post(route('password.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->get(route('password.request'))->assertDontSee('name="password"', false);
    });

    test('too many wrong codes cancel the code', function () {
        $code = requestResetCode($this->schoolAdmin);

        for ($i = 0; $i < PasswordResetCodes::MAX_ATTEMPTS; $i++) {
            $this->post(route('password.verify'), ['code' => wrongCode($code)]);
        }

        // Even the right code is now refused: a new one must be requested.
        $this->post(route('password.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        expect(DB::table('password_reset_tokens')->count())->toBe(0);
    });

    test('asking again straight away does not send another code', function () {
        requestResetCode($this->schoolAdmin);

        $this->post(route('password.email'), ['email' => $this->schoolAdmin->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    });

    test('the password fields cannot be reached without verifying a code', function () {
        requestResetCode($this->schoolAdmin);

        // Posting straight to the last step, with a made-up session key.
        $this->withSession(['password_reset.key' => str_repeat('x', 64)])
            ->post(route('password.store'), [
                'password' => 'Brand!New2Pass',
                'password_confirmation' => 'Brand!New2Pass',
            ])->assertSessionHasErrors('email');

        expect(Hash::check('Old!Passw0rd', $this->schoolAdmin->fresh()->password))->toBeTrue();
    });

    test('the new password must meet the rules and match its confirmation', function () {
        $code = requestResetCode($this->schoolAdmin);
        $this->post(route('password.verify'), ['code' => $code]);

        $this->post(route('password.store'), ['password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password');

        $this->post(route('password.store'), ['password' => 'Brand!New2Pass', 'password_confirmation' => 'Different!2Pass'])
            ->assertSessionHasErrors('password');

        $this->post(route('password.store'), ['password' => 'Greenfield College1!', 'password_confirmation' => 'Greenfield College1!'])
            ->assertSessionHasErrors('password');

        expect(Hash::check('Old!Passw0rd', $this->schoolAdmin->fresh()->password))->toBeTrue();
    });

    test('a successful reset tells the account holder and is audited', function () {
        $code = requestResetCode($this->schoolAdmin);
        $this->post(route('password.verify'), ['code' => $code]);
        $this->post(route('password.store'), ['password' => 'Brand!New2Pass', 'password_confirmation' => 'Brand!New2Pass']);

        Notification::assertSentTo($this->schoolAdmin, PasswordChangedNotification::class);

        $this->assertDatabaseHas('audit_logs', ['action' => 'password-reset.requested']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password-reset.completed']);
    });

    test('a burst of requests from one address is cut off', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "person{$i}@example.test"]);
        }

        $this->post(route('password.email'), ['email' => 'person99@example.test'])
            ->assertStatus(429);
    });

    test('the parallel and link-based reset routes are gone', function () {
        foreach (['password.reset', 'admin.password-reset.request', 'admin.password-reset.show', 'admin.password-reset.complete'] as $name) {
            expect(Route::has($name))->toBeFalse();
        }
    });
});
