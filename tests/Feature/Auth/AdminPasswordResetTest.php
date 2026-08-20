<?php

use App\Enums\UserRole;
use App\Models\AdminPasswordReset;
use App\Models\AuditLog;
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
            ->assertSee('Verification Code');

        return true;
    });
});

test('an unknown reset token shows the invalid-link page', function () {
    $this->get(route('admin.password-reset.show', 'not-a-real-token'))
        ->assertOk()
        ->assertSee('Invalid or Has Expired');
});

test('an expired reset token shows the invalid-link page', function () {
    Notification::fake();
    $this->post(route('admin.password-reset.send'), ['email' => $this->admin->email]);

    Notification::assertSentTo($this->admin, AdminPasswordResetRequested::class, function ($notification) {
        AdminPasswordReset::where('user_id', $this->admin->id)->update(['expires_at' => now()->subMinute()]);

        $this->get(route('admin.password-reset.show', $notification->linkToken))
            ->assertOk()
            ->assertSee('Invalid or Has Expired');

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
            ->assertSee('Set Your New Password');

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
            ->assertSee('Invalid or Has Expired');

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

        $response->assertRedirect(route('login'));

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
            ->assertSee('Invalid or Has Expired');

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
