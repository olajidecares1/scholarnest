<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The 6-digit codes behind "Forgot password?".
 *
 * Who may use it: AkademicNest Team (Super Admin) and School Admin accounts
 * that are active. Staff, students and parents have no self-service reset;
 * their School Admin resets them. See docs/PASSWORD-RESET-POLICY.md.
 *
 * One row per email address in password_reset_tokens, so asking for a new
 * code replaces the old one, which stops working at that moment.
 *
 * A CODE IS NEVER STORED IN READABLE FORM. The row holds its hash. It expires
 * after EXPIRES_MINUTES, allows MAX_ATTEMPTS wrong guesses before the row is
 * deleted, and is spent the moment it is verified: the hash is replaced with
 * the hash of a one-time reset key held only in the person's session, so the
 * same code cannot be entered a second time, and the password can only be set
 * by the browser that entered it.
 */
class PasswordResetCodes
{
    public const EXPIRES_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    /**
     * A new code is not sent to the same address more often than this.
     */
    public const RESEND_AFTER_SECONDS = 60;

    public const VERIFIED = 'verified';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    /**
     * The account a code may be sent to, or null.
     */
    public function eligibleUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->whereIn('role', [UserRole::SuperAdmin, UserRole::SchoolAdmin])
            ->where('is_active', true)
            ->first();
    }

    /**
     * Whether a code went to this address too recently to send another.
     */
    public function recentlyIssued(string $email): bool
    {
        $createdAt = $this->row($email)?->created_at;

        return $createdAt !== null
            && Carbon::parse($createdAt)->gt(now()->subSeconds(self::RESEND_AFTER_SECONDS));
    }

    /**
     * Create a code for this account, replacing any earlier one.
     *
     * @return string the plain code, for the email and nowhere else
     */
    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table($this->table())->where('email', $this->key($user->email))->delete();

        DB::table($this->table())->insert([
            'email' => $this->key($user->email),
            'token' => Hash::make($code),
            'attempts' => 0,
            'verified_at' => null,
            'created_at' => now(),
        ]);

        return $code;
    }

    /**
     * Throw away the code for this address.
     */
    public function discard(string $email): void
    {
        DB::table($this->table())->where('email', $this->key($email))->delete();
    }

    /**
     * Check a code.
     *
     * @return array{0: string, 1: string|null} [outcome, reset key when verified]
     */
    public function verify(string $email, string $code): array
    {
        $row = $this->row($email);

        if ($row === null || $row->verified_at !== null) {
            // No code, or one that has already been used. Answered exactly as a
            // wrong code, so an address with no account looks the same.
            Hash::check($code, '$2y$12$'.str_repeat('a', 53));

            return [self::INVALID, null];
        }

        if (Carbon::parse($row->created_at)->lte(now()->subMinutes(self::EXPIRES_MINUTES))) {
            $this->discard($email);

            return [self::EXPIRED, null];
        }

        if (! Hash::check($code, $row->token)) {
            $attempts = (int) $row->attempts + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->discard($email);

                return [self::LOCKED, null];
            }

            DB::table($this->table())->where('email', $row->email)->update(['attempts' => $attempts]);

            return [self::INVALID, null];
        }

        // Spent. The code's hash is replaced by the hash of a key only this
        // browser holds, so the code cannot be verified again.
        $resetKey = Str::random(64);

        DB::table($this->table())->where('email', $row->email)->update([
            'token' => hash('sha256', $resetKey),
            'verified_at' => now(),
        ]);

        return [self::VERIFIED, $resetKey];
    }

    /**
     * Whether a verified code still permits setting the password.
     */
    public function mayReset(string $email, string $resetKey): bool
    {
        $row = $this->row($email);

        return $row !== null
            && $row->verified_at !== null
            && Carbon::parse($row->created_at)->gt(now()->subMinutes(self::EXPIRES_MINUTES * 2))
            && hash_equals((string) $row->token, hash('sha256', $resetKey));
    }

    private function row(string $email): ?object
    {
        return DB::table($this->table())->where('email', $this->key($email))->first();
    }

    private function key(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function table(): string
    {
        return (string) config('auth.passwords.users.table', 'password_reset_tokens');
    }
}
