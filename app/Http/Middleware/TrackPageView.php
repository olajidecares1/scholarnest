<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_PREFIXES = ['super-admin', 'notifications', 'up'];

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

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($request->is($prefix) || $request->is($prefix.'/*')) {
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
