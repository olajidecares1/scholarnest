<?php

use App\Enums\UserRole;
use App\Models\AdminPasswordReset;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Notifications\AdminPasswordResetRequested;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'email' => 'admin@example.com']);
});

test('requesting a reset for a real email creates a pending reset and sends the notification', function () {
    Notification::fake();

    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email])
        ->assertSessionHasNoErrors();

    expect(AdminPasswordReset::where('user_id', $this->admin->id)->count())->toBe(1);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class);
});

test('requesting a reset for an unknown email gives the same generic response and creates nothing', function () {
    Notification::fake();

    $response = $this->post(route('admin.password-reset.send'), ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('status');

    expect(AdminPasswordReset::count())->toBe(0);
    Notification::assertNothingSent();
});

test('a new reset request invalidates a previously pending one', function () {
    Notification::fake();

    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);
    $first = AdminPasswordReset::where('user_id', $this->admin->id)->firstOrFail();

    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    expect(AdminPasswordReset::where('id', $first->id)->exists())->toBeFalse();
    expect(AdminPasswordReset::where('user_id', $this->admin->id)->count())->toBe(1);
});

test('reset requests are rate limited', function () {
    Notification::fake();

    for ($i = 0; $i < 3; $i++) {
        $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);
    }

    $response = $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    $response->assertSessionHasErrors('email');
});

test('visiting the reset link with an unverified code shows the code-entry step', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertOk()
            ->assertSee('Check your email');

        return true;
    });
});

test('an unknown reset token shows the invalid-link page', function () {
    $this->get(route('admin.password-reset.show', 'not-a-real-token'))
        ->assertOk()
        ->assertSee('This link has expired');
});

test('an expired reset token shows the invalid-link page', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        AdminPasswordReset::where('user_id', $this->admin->id)->update(['expires_at' => now()->subMinute()]);

        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertOk()
            ->assertSee('This link has expired');

        return true;
    });
});

test('the correct 6-digit code advances the flow to the new-password step', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => $notification->code])
            ->assertRedirect(route('admin.password-reset.show', $notification->linkToken));

        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertOk()
            ->assertSee('Create a new password');

        return true;
    });
});

test('the code must be exactly 6 digits and correct', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $reset = AdminPasswordReset::where('user_id', $this->admin->id)->firstOrFail();
        expect($reset->code_verified_at)->toBeNull();

        return true;
    });
});

test('the reset flow is invalidated after five wrong code attempts', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => '000000']);
        }

        // The 5 wrong guesses should have invalidated the reset outright -
        // even the CORRECT code must no longer work afterward.
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => $notification->code]);

        $reset = AdminPasswordReset::where('user_id', $this->admin->id)->first();
        expect($reset->code_verified_at)->toBeNull();

        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertSee('This link has expired');

        return true;
    });
});

test('completing the reset without a verified code redirects back to the code step', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.complete', $notification->linkToken), [
            'password' => 'NewStrong@123',
            'password_confirmation' => 'NewStrong@123',
        ])->assertRedirect(route('admin.password-reset.show', $notification->linkToken));

        expect(Hash::check('NewStrong@123', $this->admin->fresh()->password))->toBeFalse();

        return true;
    });
});

test('a weak new password is rejected server-side', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => $notification->code]);

        $this->post(route('admin.password-reset.complete', $notification->linkToken), [
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        return true;
    });
});

test('the full flow resets the password, logs the action, and notifies the admin', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => $notification->code]);

        $response = $this->post(route('admin.password-reset.complete', $notification->linkToken), [
            'password' => 'NewStrong@123',
            'password_confirmation' => 'NewStrong@123',
        ]);

        $response->assertRedirect(route('admin.password-reset.done'));

        expect(Hash::check('NewStrong@123', $this->admin->fresh()->password))->toBeTrue();

        $reset = AdminPasswordReset::where('user_id', $this->admin->id)->firstOrFail();
        expect($reset->used_at)->not->toBeNull();

        expect(AuditLog::where('action', 'password.reset')->where('subject_id', $this->admin->id)->exists())->toBeTrue();

        Notification::assertSentTo($this->admin, PasswordChangedNotification::class);

        return true;
    });
});

test('a used reset link cannot be reused', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $this->post(route('admin.password-reset.verify-code', $notification->linkToken), ['code' => $notification->code]);
        $this->post(route('admin.password-reset.complete', $notification->linkToken), [
            'password' => 'NewStrong@123',
            'password_confirmation' => 'NewStrong@123',
        ]);

        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertSee('This link has expired');

        return true;
    });
});

test('a deactivated admin cannot trigger a password reset', function () {
    Notification::fake();
    $this->admin->update(['is_active' => false]);

    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    expect(AdminPasswordReset::count())->toBe(0);
    Notification::assertNothingSent();
});

// -----------------------------------------------------------------------------
// Resending a code
// -----------------------------------------------------------------------------

/**
 * Start a reset and hand back the link token from the emailed notification.
 */
function startResetFor(User $admin): string
{
    Notification::fake();

    test()->post(route('admin.password-reset.send'), ['email' => $admin->email]);

    $sent = null;
    Notification::assertSentTo($admin, AdminPasswordResetRequested::class, function ($notification) use (&$sent) {
        $sent = $notification;

        return true;
    });

    return $sent->linkToken;
}

test('a resend issues a new code and emails it again', function () {
    $token = startResetFor($this->admin);
    $original = AdminPasswordReset::where('user_id', $this->admin->id)->firstOrFail()->code_hash;

    $this->post(route('admin.password-reset.resend', $token))
        ->assertSessionHas('status');

    // A different code, sent to the same person.
    expect(AdminPasswordReset::where('user_id', $this->admin->id)->firstOrFail()->code_hash)
        ->not->toBe($original);

    Notification::assertSentToTimes($this->admin, AdminPasswordResetRequested::class, 2);
});

test('a resend leaves the emailed link working', function () {
    $token = startResetFor($this->admin);

    $this->post(route('admin.password-reset.resend', $token));

    // Rotating the link would kill the page the person is standing on, and
    // the link in the email they are reading.
    $this->get(route('admin.password-reset.show', $token))
        ->assertOk()
        ->assertSee('Check your email');
});

test('the new code works and the previous one does not', function () {
    $token = startResetFor($this->admin);

    $firstCode = null;
    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($n) use (&$firstCode) {
        $firstCode ??= $n->code;

        return true;
    });

    $this->post(route('admin.password-reset.resend', $token));

    $codes = [];
    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($n) use (&$codes) {
        $codes[] = $n->code;

        return true;
    });

    $latest = end($codes);

    $this->post(route('admin.password-reset.verify-code', $token), ['code' => $firstCode])
        ->assertSessionHasErrors('code');

    session()->forget('errors');

    $this->post(route('admin.password-reset.verify-code', $token), ['code' => $latest])
        ->assertSessionHasNoErrors();
});

test('resending twice in quick succession is refused', function () {
    $token = startResetFor($this->admin);

    $this->post(route('admin.password-reset.resend', $token))->assertSessionHas('status');

    // One a minute: the countdown on the page reflects this exactly, so the
    // button re-enables when the server would actually accept another.
    $this->post(route('admin.password-reset.resend', $token))
        ->assertSessionHasErrors('code');

    Notification::assertSentToTimes($this->admin, AdminPasswordResetRequested::class, 2);
});

test('a resend against an unknown token goes nowhere', function () {
    Notification::fake();

    $this->post(route('admin.password-reset.resend', 'not-a-real-token'))
        ->assertRedirect(route('admin.password-reset.show', 'not-a-real-token'));

    Notification::assertNothingSent();
});

// -----------------------------------------------------------------------------
// The success screen
// -----------------------------------------------------------------------------

test('a school admin finishing the reset is pointed at their own school portal', function () {
    $school = School::factory()->create();
    $this->admin->update(['school_id' => $school->id]);

    $token = startResetFor($this->admin);

    $code = null;
    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->post(route('admin.password-reset.verify-code', $token), ['code' => $code]);

    $this->post(route('admin.password-reset.complete', $token), [
        'password' => 'NewSecure@123',
        'password_confirmation' => 'NewSecure@123',
    ])->assertRedirect(route('admin.password-reset.done'));

    // Straight back to the door this person actually uses.
    $this->get(route('admin.password-reset.done'))
        ->assertOk()
        ->assertSee('Password reset successful')
        ->assertSee($school->portalLoginUrl('web'), false);
});

test('a super admin finishing the reset is pointed at the platform front door', function () {
    $superAdmin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'email' => 'super@example.com',
    ]);

    $token = startResetFor($superAdmin);

    $code = null;
    Notification::assertSentTo($superAdmin, AdminPasswordResetRequested::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->post(route('admin.password-reset.verify-code', $token), ['code' => $code]);

    $this->post(route('admin.password-reset.complete', $token), [
        'password' => 'NewSecure@123',
        'password_confirmation' => 'NewSecure@123',
    ]);

    // No school of their own, so the registration page - which is where the
    // hidden Super Admin dialog lives.
    $this->get(route('admin.password-reset.done'))
        ->assertOk()
        ->assertSee(route('register'), false);
});

test('the reset email tells the reader to keep the code to themselves', function () {
    Notification::fake();

    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        $rendered = (string) $notification->toMail($this->admin)->render();

        return str_contains($rendered, 'never ask you for it')
            && str_contains($rendered, $notification->code);
    });
});
