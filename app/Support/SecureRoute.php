<?php

namespace App\Support;

class SecureRoute
{
    /**
     * Bump this if every generated URL must change (e.g. after a suspected leak).
     */
    /**
     * DELIBERATELY STILL "akademicnest", after the platform was renamed to
     * AkademicNest.
     *
     * This string is not a name. Every obfuscated URL in the application is a
     * SHA-512 of it plus a route seed, so changing it regenerates all of them
     * at once - every AkademicNest Team page, every School Admin page - and kills
     * every bookmark anyone holds. A rename is not a reason to do that.
     *
     * It is versioned for the one case that IS a reason: a suspected leak. Bump
     * it to -v2 then, knowing exactly what it costs.
     */
    private const SALT = 'edunest-secure-route-v1';

    /**
     * Deterministically derive an opaque, unguessable URI segment for a route.
     *
     * The seed only has to be unique within routes/*.php - it never appears
     * in the generated token and has no relationship to the final route name.
     */
    public static function uri(string $seed): string
    {
        return hash('sha512', self::SALT.'|'.$seed);
    }
}
