<?php

namespace App\Services;

use App\Models\PortalSession;
use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues, resolves, and revokes the opaque per-login-session tokens that
 * back the /portal/{token} URL scheme. The token is a routing/obfuscation
 * layer only - resolve() intentionally never returns anything that could
 * be used as a credential on its own; the caller still has to independently
 * confirm an authenticated guard session before treating a resolved token
 * as meaningful (see App\Http\Middleware\ResolvePortalSession, added in a
 * later phase).
 */
class PortalSessionBroker
{
    private const TOKEN_LENGTH = 40;

    private const DEFAULT_TTL_HOURS = 12;

    private const MAX_ISSUE_ATTEMPTS = 3;

    private const STALE_TOUCH_SECONDS = 60;

    public function issue(School $school, string $guard, Model $user, string $laravelSessionId): PortalSession
    {
        for ($attempt = 1; $attempt <= self::MAX_ISSUE_ATTEMPTS; $attempt++) {
            $token = Str::random(self::TOKEN_LENGTH);

            if (PortalSession::where('token', $token)->exists()) {
                continue;
            }

            return PortalSession::create([
                'token' => $token,
                'school_id' => $school->id,
                'guard' => $guard,
                'authenticatable_type' => $user->getMorphClass(),
                'authenticatable_id' => $user->getKey(),
                'laravel_session_id' => $laravelSessionId,
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ]);
        }

        throw new RuntimeException('Could not generate a unique portal session token.');
    }

    public function resolve(string $token, string $guard, string $laravelSessionId): ?PortalSession
    {
        $portalSession = PortalSession::active()
            ->where('token', $token)
            ->where('guard', $guard)
            ->first();

        if (! $portalSession) {
            return null;
        }

        if (! hash_equals($portalSession->laravel_session_id, $laravelSessionId)) {
            return null;
        }

        $this->touch($portalSession);

        return $portalSession;
    }

    public function revoke(string $token): void
    {
        PortalSession::where('token', $token)->update(['revoked_at' => now()]);
    }

    public function revokeForSession(string $laravelSessionId, string $guard): void
    {
        PortalSession::where('laravel_session_id', $laravelSessionId)
            ->where('guard', $guard)
            ->update(['revoked_at' => now()]);
    }

    private function touch(PortalSession $portalSession): void
    {
        $isStale = ! $portalSession->last_used_at
            || $portalSession->last_used_at->diffInSeconds(now(), absolute: true) > self::STALE_TOUCH_SECONDS;

        if ($isStale) {
            $portalSession->update(['last_used_at' => now()]);
        }
    }
}
