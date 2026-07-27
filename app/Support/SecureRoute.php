<?php

namespace App\Support;

class SecureRoute
{
    /**
     * Bump this if every generated URL must change (e.g. after a suspected leak).
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
