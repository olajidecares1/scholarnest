{{-- A school's own address, before it has published a website.

     Self-contained: no @vite, no site layout, no website record, because the
     whole point is that there is no website record to render from. It has the
     School, so it wears the school's name, badge and colour and nobody else's.

     What it must do, in order: say the address is right, get somebody into the
     portal that already works there, and tell the school's administrator what
     is missing. --}}
@php
    $brand = $school->website?->brand_primary_color;
    $brand = is_string($brand) && preg_match('/^#[0-9a-fA-F]{6}$/', $brand) ? $brand : '#0F2A5C';
    $logo = $school->logoUrl();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- An unfinished site must not become the school's search result. --}}
    <meta name="robots" content="noindex">
    <title>{{ $school->name }}</title>
    <x-favicon :school="$school" />
    <style>
        :root { color-scheme: light dark; --brand: {{ $brand }}; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               padding: 24px 16px; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               background: #F4F7FB; color: #0F2A5C; }
        .card { width: 100%; max-width: 560px; background: #fff; border: 1px solid #E1E8F2; border-radius: 16px;
                padding: 40px 28px; text-align: center; box-shadow: 0 18px 50px -24px rgba(15,42,92,.35); }
        .badge { width: 84px; height: 84px; margin: 0 auto 20px; border-radius: 14px; object-fit: cover;
                 display: flex; align-items: center; justify-content: center; background: var(--brand);
                 color: #fff; font-size: 2rem; font-weight: 800; }
        h1 { font-size: 1.5rem; line-height: 1.3; margin: 0 0 6px; }
        .status { display: inline-block; margin: 0 0 18px; padding: 4px 12px; border-radius: 999px;
                  background: #EEF3FB; color: #3C5A8A; font-size: .8rem; font-weight: 600; }
        p { margin: 0 0 22px; line-height: 1.6; color: #3C5A8A; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        a.button { display: inline-block; padding: 12px 22px; border-radius: 10px; text-decoration: none;
                   font-weight: 600; font-size: .95rem; }
        a.primary { background: var(--brand); color: #fff; }
        a.secondary { background: #EEF3FB; color: #1F3F73; }
        .admin { margin: 26px 0 0; padding-top: 20px; border-top: 1px solid #EDF1F7;
                 font-size: .85rem; color: #6B82A6; line-height: 1.6; }
        .admin strong { color: #3C5A8A; }
        @media (prefers-color-scheme: dark) {
            body { background: #0B1728; color: #E8EEF7; }
            .card { background: #12203A; border-color: #1E3355; }
            p, .admin { color: #9DB2D1; }
            .status { background: #1B2E4F; color: #9DB2D1; }
            a.secondary { background: #1B2E4F; color: #D7E3F5; }
        }
    </style>
</head>
<body>
    <main class="card">
        @if ($logo)
            <img class="badge" src="{{ $logo }}" alt="{{ $school->name }}">
        @else
            <div class="badge">{{ \Illuminate\Support\Str::of($school->name)->substr(0, 1)->upper() }}</div>
        @endif

        <h1>{{ $school->name }}</h1>
        <span class="status">Website coming soon</span>

        <p>
            You are at the right address. This school's website has not been
            published yet, but its portal is already open.
        </p>

        <div class="actions">
            <a class="button primary" href="{{ $portalUrl }}">Go to the portal</a>

            @if ($resultsUrl)
                <a class="button secondary" href="{{ $resultsUrl }}">Check a result</a>
            @endif
        </div>

        <p class="admin">
            <strong>Are you this school's administrator?</strong>
            Sign in through the portal and publish your site from
            Website &rsaquo; Publish. It appears here as soon as you do.
        </p>
    </main>
</body>
</html>
