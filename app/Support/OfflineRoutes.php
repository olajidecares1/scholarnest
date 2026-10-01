<?php

namespace App\Support;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The addresses the offline script must treat specially, by ROUTE NAME.
 *
 * The School Admin's pages live at obfuscated paths (see App\Support\R), so
 * "/subscriptions/pay" is a 128-character hash and nothing about the path
 * says it is a payment or a sign-out. The route NAME still does, so the lists
 * are built here from the names and handed to the page as path patterns.
 */
class OfflineRoutes
{
    /**
     * Writes that need the server there and then, and cannot sensibly wait
     * in a queue: signing in, passwords, payments, unlocking a result with a
     * PIN (the answer IS the result).
     *
     * @var list<string>
     */
    private const ONLINE_ONLY = [
        '*login*',
        '*register*',
        'password.*',
        '*.password.*',
        '*password*',
        'verification.*',
        '*two-factor*',
        'subscriptions.*',
        '*payment*',
        '*paystack*',
        '*flutterwave*',
        '*checkout*',
        '*billing*',
        '*unlock*',
        '*impersonat*',
    ];

    /** @var list<string> */
    private const LOGOUT = ['*logout*'];

    /**
     * Pages never fetched in the background: opening them does something
     * (marks a notification read, signs out), or they are files, not pages.
     *
     * @var list<string>
     */
    private const NEVER_WARM = [
        '*logout*',
        '*download*',
        '*export*',
        '*.pdf',
        '*pdf*',
        '*stream*',
        '*impersonat*',
        'notifications.read',
        '*.read',
        '*keep-alive*',
        '*destroy*',
        '*delete*',
        '*.print*',
        '*csv*',
        '*template*.sample',
        'pwa.*',
    ];

    /** @var array<string, list<string>>|null */
    private static ?array $cache = null;

    /**
     * @return array{onlineOnly: list<string>, logout: list<string>, neverWarm: list<string>}
     */
    public static function patterns(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $onlineOnly = [];
        $logout = [];
        $neverWarm = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '' || str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $writes = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [];
            $pattern = self::pathPattern($route->uri());

            if ($writes && Str::is(self::LOGOUT, $name)) {
                $logout[] = $pattern;
            } elseif ($writes && Str::is(self::ONLINE_ONLY, $name)) {
                $onlineOnly[] = $pattern;
            }

            if (in_array('GET', $route->methods(), true) && Str::is(self::NEVER_WARM, $name)) {
                $neverWarm[] = $pattern;
            }
        }

        return self::$cache = [
            'onlineOnly' => array_values(array_unique($onlineOnly)),
            'logout' => array_values(array_unique($logout)),
            'neverWarm' => array_values(array_unique($neverWarm)),
        ];
    }

    /**
     * A route's URI as a JavaScript regular expression source matching the
     * path: /p/{school}/staff-portal/logout becomes ^/p/[^/]+/staff-portal/logout/?$.
     */
    private static function pathPattern(string $uri): string
    {
        $parts = preg_split('/(\{[^}]+\})/', '/'.ltrim($uri, '/'), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $regex = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{[^}]+\?\}$/', $part)) {
                $regex .= '[^/]*';
            } elseif (preg_match('/^\{[^}]+\}$/', $part)) {
                $regex .= '[^/]+';
            } else {
                $regex .= preg_quote($part, '/');
            }
        }

        return '^'.rtrim($regex, '/').'/?$';
    }
}
