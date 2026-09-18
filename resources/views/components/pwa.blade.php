@props(['school', 'portal'])

@php
    $app = $portal instanceof \App\Enums\PortalApp ? $portal : \App\Enums\PortalApp::from($portal);
    $pwa = new \App\Support\PortalPwa($school, $app);

    // Built here rather than inline in @json below. Blade parses a directive's
    // argument by matching brackets, and a multi-line array with string
    // concatenation in it defeats that, it reads the first "]" it likes and
    // reports an unclosed bracket, swallowing the rest of the file.
    $pwaSettings = [
        'name' => $pwa->shortName(),
        'portal' => $app->value,
        'portalLabel' => $app->label(),
        'school' => $school->name,
        'startUrl' => $app->startUrl($school),
        'serviceWorker' => route('pwa.service-worker', absolute: false),
        // One key per school per portal, so dismissing the prompt on a teacher
        // account does not also dismiss it on a parent one on the same phone.
        'dismissKey' => 'pwa-dismissed:'.$school->portal_key.':'.$app->value,
    ];
@endphp

{{--
    What makes this portal installable, and what makes the installed thing
    open THIS school rather than any other.

    The manifest is per school per portal, so a parent with children at two
    schools gets two icons and a teacher who is also a parent gets two more.
    Every path inside it is relative, so an install made on the platform host,
    on a school's subdomain or on its own domain each stays where it was made.
--}}
<link rel="manifest" href="{{ $pwa->manifestUrl() }}">
<meta name="theme-color" content="{{ $pwa->themeColor() }}">
<meta name="application-name" content="{{ $pwa->shortName() }}">

{{--
    iOS reads none of the manifest. Everything Safari needs to put an icon on a
    home screen is in these four tags, and without them an "Add to Home Screen"
    there produces a screenshot of the page under the site's URL instead of an
    app.
--}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwa->shortName() }}">
<link rel="apple-touch-icon" href="{{ $pwa->iconUrl(180) }}">

<script>
    // Handed to the install prompt so it can name the app, and to the service
    // worker registration. Read from the DOM rather than bundled, because
    // every value here is per school and the bundle is shared by all of them.
    window.AkademicNestPwa = @json($pwaSettings);

    // CAUGHT HERE, IN THE HEAD, and not in the bundle. A browser fires
    // beforeinstallprompt once and does not fire it again; the bundle is a
    // module and therefore deferred, so on a fast connection the event can
    // come and go before a listener there exists, and the install offer
    // silently never appears. This stashes the event for resources/js/pwa.js
    // to pick up whenever it runs.
    window.AkademicNestPwaPrompt = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.AkademicNestPwaPrompt = event;
        window.dispatchEvent(new CustomEvent('akademicnest:installable'));
    });
</script>
