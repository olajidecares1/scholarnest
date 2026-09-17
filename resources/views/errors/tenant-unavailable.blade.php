{{-- What a school address shows when there is no website to serve there.

     Self-contained on purpose: no @vite, no school branding, no school data.
     It is rendered BEFORE a tenant is bound, for addresses that resolved to no
     school or to one that may not be served, so nothing school-specific is
     available to it and nothing school-specific should leak through it. --}}
@php
    $platform = config('app.name', 'AkademicNest');

    [$heading, $body] = match ($reason) {
        'suspended' => [
            'This school website is temporarily unavailable',
            'The school’s account is currently paused. If you are the school’s administrator, please contact '.$platform.' support.',
        ],
        'subscription' => [
            'This school website is temporarily unavailable',
            'The school’s subscription is not active at the moment. If you are the school’s administrator, sign in to renew it.',
        ],
        default => [
            'No school website at this address',
            'Check the address and try again. School website addresses look like yourschool.'.(config('custom_domain.tenant_base_domain') ?: parse_url($platformUrl, PHP_URL_HOST)).'.',
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} · {{ $platform }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               padding: 24px 16px; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               background: #F4F7FB; color: #0F2A5C; }
        .card { width: 100%; max-width: 520px; background: #fff; border: 1px solid #E1E8F2; border-radius: 14px;
                padding: 32px 24px; text-align: center; box-shadow: 0 18px 50px -24px rgba(15,42,92,.35); }
        h1 { font-size: 1.35rem; line-height: 1.3; margin: 0 0 12px; }
        p { margin: 0 0 24px; color: #4A5B78; line-height: 1.6; }
        a.button { display: inline-block; padding: 11px 20px; border-radius: 8px; background: #1877F2; color: #fff;
                   text-decoration: none; font-weight: 600; }
        .brand { font-weight: 700; letter-spacing: .02em; color: #1877F2; margin-bottom: 20px; }
        @media (prefers-color-scheme: dark) {
            body { background: #0B1426; color: #E6ECF5; }
            .card { background: #111D33; border-color: #22324F; }
            p { color: #A9B6CC; }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">{{ $platform }}</div>
        <h1>{{ $heading }}</h1>
        <p>{{ $body }}</p>
        <a class="button" href="{{ $platformUrl }}">Go to {{ $platform }}</a>
    </main>
</body>
</html>
