<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    /**
     * Route-name prefixes to exclude from public traffic analytics.
     * Route names stay stable even though every URI is an opaque token,
     * so this, not the URL, is what exclusion has to key off of.
     *
     * @var list<string>
     */
    /**
     * Routes that are not pages. The file routes serve images a page asks for,
     * a school logo, a photograph, an icon, and counting each one recorded
     * several "page views" for every page anybody opened.
     */
    private const EXCLUDED_ROUTE_NAME_PREFIXES = ['super-admin.', 'notifications.', 'stored-files.', 'branding.', 'media.', 'pwa.', 'session.'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            PageView::create([
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'referrer_host' => $this->refererHost($request),
                'traffic_source' => $this->trafficSource($request),
                'device_type' => $this->deviceType((string) $request->userAgent()),
                'ip_address' => $request->ip(),
                'viewed_at' => now(),
            ]);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($request->is('up')) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return true;
        }

        foreach (self::EXCLUDED_ROUTE_NAME_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function refererHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return $host && $host !== $request->getHost() ? $host : null;
    }

    private function trafficSource(Request $request): string
    {
        $host = $this->refererHost($request);

        if (! $host) {
            return 'direct';
        }

        if (preg_match('/google\.|bing\.|yahoo\.|duckduckgo\./i', $host)) {
            return 'search';
        }

        if (preg_match('/facebook\.|twitter\.|x\.com|instagram\.|linkedin\.|tiktok\./i', $host)) {
            return 'social';
        }

        return 'referral';
    }

    private function deviceType(string $userAgent): string
    {
        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|Android/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
