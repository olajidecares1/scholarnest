<?php

namespace App\Support;

class TenantUrl
{
    /**
     * Builds an absolute URL for a tenant domain/subdomain host, using the
     * scheme and port this app is actually configured to run on (APP_URL)
     * rather than assuming production's https-with-no-port, otherwise the
     * generated link is unreachable in local dev, where the app is served
     * over plain http on a non-standard port (e.g. "php artisan serve").
     */
    public static function build(string $host, string $path, ?string $query = null): string
    {
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);

        $portSuffix = $port && ! in_array($port, [80, 443], true) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$portSuffix}{$path}".($query ? "?{$query}" : '');
    }
}
