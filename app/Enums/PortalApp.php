<?php

namespace App\Enums;

use App\Models\School;

/**
 * The four portals, as installable applications.
 *
 * A school does not have "an app" - it has four, one per audience, and they
 * are genuinely different products: a parent's app opens on their children's
 * results, a teacher's on the classes they take. Installing one must never
 * land somebody in another, and installing the same portal for two different
 * schools must produce two apps on the home screen, not one that overwrites
 * the other.
 *
 * Everything that makes an installed app THIS school's portal is derived from
 * a School the caller already resolved, so a manifest can never be built for a
 * school the requester did not legitimately reach.
 */
enum PortalApp: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Guardian = 'guardian';
    case Admin = 'admin';

    /**
     * The guard this portal signs in on. Also the key everything else is
     * derived from, so these cannot drift apart.
     */
    public function guard(): string
    {
        return match ($this) {
            self::Student => 'student',
            self::Staff => 'staff',
            self::Guardian => 'guardian',
            self::Admin => 'web',
        };
    }

    /**
     * What the app is called on the home screen, under the school's name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student Portal',
            self::Staff => 'Staff Portal',
            self::Guardian => 'Parent Portal',
            self::Admin => 'School Admin',
        };
    }

    /**
     * The short name, for the few characters a home screen actually shows.
     *
     * A launcher gives roughly twelve before it truncates, and "Greenfield
     * College - Student Portal" truncated is indistinguishable from the same
     * school's parent app. The portal is the distinguishing half, so the
     * portal is what goes here.
     */
    public function shortName(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Staff => 'Staff',
            self::Guardian => 'Parent',
            self::Admin => 'Admin',
        };
    }

    /**
     * Where launching the installed app lands.
     *
     * The DASHBOARD, not the login page, for the three portals that have one:
     * somebody with a live session goes straight in, and somebody without is
     * redirected to their own school's login by the auth middleware - see
     * App\Support\PortalLoginRedirect, which resolves the school from the
     * /p/{portal_key}/ segment that is right there in this URL.
     *
     * The School Admin dashboard is an obfuscated path with no school in it,
     * so that one cannot serve as an entry point: launched by somebody signed
     * out it would have nothing to say which school this app belongs to. Its
     * start URL is the school's own admin login instead, which redirects to
     * the dashboard when a session already exists.
     */
    public function startUrl(School $school): string
    {
        return match ($this) {
            self::Student => route('student.dashboard', $school, absolute: false),
            self::Staff => route('staff.dashboard', $school, absolute: false),
            self::Guardian => route('guardian.dashboard', $school, absolute: false),
            self::Admin => $this->relative($school->portalLoginUrl('web')),
        };
    }

    /**
     * The paths that belong to this app rather than to the browser.
     *
     * A link outside the scope opens in a normal tab, which is what should
     * happen to a school's public website or a result link shared by a parent.
     *
     * The School Admin's scope is the whole origin because its pages are
     * obfuscated root paths with nothing in common to match on. That is not a
     * tenancy hole: scope decides which window a link opens in, never what
     * anybody may read, and every page behind it is still gated by the session
     * and by school_id on the server.
     */
    public function scope(School $school): string
    {
        return match ($this) {
            self::Admin => '/',
            default => $this->parentPath($this->startUrl($school)),
        };
    }

    /**
     * A stable identity for the installed app.
     *
     * Without this a browser identifies an app by its start URL, and two
     * schools' student portals differ only by an opaque key deep in the path -
     * close enough that a changed start URL later would be read as the same
     * app moving rather than a different one. The id never changes, so an
     * installed app survives a change of entry point.
     */
    public function manifestId(School $school): string
    {
        return "/pwa/{$school->portal_key}/{$this->value}";
    }

    /**
     * Strip everything after the last segment: /p/k/portal/dashboard becomes
     * /p/k/portal/. The trailing slash matters - a scope without one matches
     * by string prefix, so "/p/k/portal" would also claim "/p/k/portal-x".
     */
    private function parentPath(string $path): string
    {
        $trimmed = rtrim($path, '/');
        $cut = strrpos($trimmed, '/');

        return $cut === false ? '/' : substr($trimmed, 0, $cut + 1);
    }

    /**
     * Keep the path and query of an absolute URL, drop the origin, so a
     * manifest served from any host resolves it against that same host.
     */
    private function relative(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "{$path}?{$query}" : $path;
    }
}
