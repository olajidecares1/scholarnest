<?php

namespace App\Services;

use App\Models\AdminPasswordReset;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AdminPasswordResetRequested;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The School Admin (and Super Admin, since they share the "web" guard)
 * forgot-password flow: email link (a random, single-use, expiring token)
 * plus a second-factor 6-digit code delivered in the same email. The link
 * token is fine to embed in a URL - that's its whole purpose - but the
 * 6-digit code is only ever accepted via a POST body, never a query string.
 */
class AdminPasswordResetBroker
{
    private const LINK_TOKEN_LENGTH = 64;

    private const LINK_TTL_MINUTES = 30;

    private const MAX_CODE_ATTEMPTS = 5;

    public function request(User $user): void
    {
        // A new request supersedes any still-pending one for this user -
        // only the most recently issued link/code should ever work.
        AdminPasswordReset::where('user_id', $user->id)->delete();

        $linkToken = Str::random(self::LINK_TOKEN_LENGTH);
        $code = (string) random_int(100000, 999999);

        $reset = AdminPasswordReset::create([
            'user_id' => $user->id,
            'link_token_hash' => hash('sha256', $linkToken),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES),
        ]);

        $user->notify(new AdminPasswordResetRequested($linkToken, $code, $reset->expires_at));
    }

    /**
     * Issue a fresh code for a reset that is already under way.
     *
     * The link token is deliberately left alone. Only its hash is stored, so
     * it cannot be re-derived here anyway - but more importantly, rotating it
     * would kill the link in the email the person is currently looking at, and
     * "resend the code" should not invalidate the page they are standing on.
     *
     * The attempt counter resets with the code: the five tries are against the
     * code that was sent, not against the reset for all time.
     */
    public function resendCode(AdminPasswordReset $reset, string $linkToken): void
    {
        $code = (string) random_int(100000, 999999);

        $reset->update([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
        ]);

        $reset->user->notify(new AdminPasswordResetRequested($linkToken, $code, $reset->expires_at));
    }

    public function resolve(string $linkToken): ?AdminPasswordReset
    {
        return AdminPasswordReset::active()
            ->where('link_token_hash', hash('sha256', $linkToken))
            ->first();
    }

    /**
     * @return bool true if the code was correct and the reset is now ready
     *              for the final "set new password" step.
     */
    public function verifyCode(AdminPasswordReset $reset, string $code): bool
    {
        if ($reset->attempts >= self::MAX_CODE_ATTEMPTS) {
            $this->invalidate($reset);

            return false;
        }

        $reset->increment('attempts');

        if (! Hash::check($code, $reset->code_hash)) {
            if ($reset->attempts >= self::MAX_CODE_ATTEMPTS) {
                $this->invalidate($reset);
            }

            return false;
        }

        $reset->update(['code_verified_at' => now()]);

        return true;
    }

    public function complete(AdminPasswordReset $reset, string $newPassword): void
    {
        $user = $reset->user;

        $user->update(['password' => Hash::make($newPassword)]);

        $reset->update(['used_at' => now()]);

        AuditLog::record('password.reset', "{$user->name} reset their own password via the forgot-password flow.", $user, actorName: $user->name);

        // Reuses the existing confirmation-email + Log::info behaviour
        // already wired to this event (App\Listeners\LogPasswordReset) -
        // the same thing the stock Breeze reset flow fires.
        event(new PasswordReset($user));
    }

    private function invalidate(AdminPasswordReset $reset): void
    {
        $reset->update(['used_at' => now()]);
    }
}
