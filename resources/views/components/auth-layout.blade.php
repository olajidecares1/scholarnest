@props([
    'authQuestion' => null,
    'authLinkLabel' => null,
    'authLinkRoute' => null,
    'simple' => false,
    'background' => null,

    // The sticky bar across the top: logo, wordmark, and the "already have an
    // account?" link.
    //
    // Off on the PORTALS, where it was costing about 90px of height at the top
    // of a page whose whole job is one sign-in card - and on a phone that is
    // the difference between seeing the password field and having to scroll for
    // it. With it off, the logo sits on its own in the top-left corner and the
    // card gets the room back.
    //
    // Still on everywhere else, because those pages need what it holds: the
    // result checker shows the school's own badge there, and registration needs
    // the way back to sign-in.
    'header' => true,

    // Opt-in, page by page. Only the registration page turns this on, so the
    // click sequence does not quietly exist on every auth screen in the app.
    'superAdminAccess' => false,

    // A page that belongs to one school rather than to the platform - the
    // result checker, chiefly. Given one, the header wears that school's badge
    // and name instead of ScholarNest's, because a parent checking their child's
    // result should see their child's school.
    'school' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path) : asset('images/logo-icon-dark.png');

    // The school's own logo, or its name alone if it has not uploaded one.
    // Never a stand-in from somewhere else: a default logo here would be a
    // different school's badge on this school's result.
    $schoolLogoUrl = $school?->logoUrl();

    $revealClicks = (int) config('super_admin.reveal_clicks', 5);
    $revealTimeout = (int) config('super_admin.reveal_click_timeout', 2000);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'ScholarNest') }}</title>

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>
    <body class="min-h-screen bg-white font-sans text-gray-900 antialiased">
        <div
            {{-- relative so the header-less logo can be positioned against the
                 page rather than the viewport - absolute-to-viewport would
                 leave it hovering over the card as the page scrolls. --}}
            class="relative flex min-h-screen flex-col"
            @if ($superAdminAccess)
                x-data="{
                    clicks: 0,
                    timer: null,
                    // A failed attempt redirects back here, so the dialog has to
                    // reopen to show why - otherwise the error lands on a page
                    // with nothing visible to attach it to.
                    open: {{ $errors->has("login") ? "true" : "false" }},
                    registerClick() {
                        // Each click must land within the timeout of the one
                        // before it. Pause too long and the count starts over,
                        // so the sequence has to be deliberate rather than
                        // something a visitor drifts into over a few minutes.
                        clearTimeout(this.timer);
                        this.clicks++;

                        if (this.clicks >= {{ $revealClicks }}) {
                            this.clicks = 0;
                            this.open = true;
                            this.$nextTick(() => this.$refs.superAdminLogin?.focus());
                            return;
                        }

                        this.timer = setTimeout(() => { this.clicks = 0 }, {{ $revealTimeout }});
                    },
                    close() {
                        this.open = false;
                        this.clicks = 0;
                        clearTimeout(this.timer);
                    },
                }"
                @keydown.escape.window="close()"
            @endif
        >
            @unless ($header)
                {{-- No header bar: the logo alone, in the top-left corner.

                     Absolute rather than in the flow, so it takes no height
                     from the card below it - which is the point of turning the
                     header off. pointer-events-none on the wrapper with the
                     link re-enabling them keeps the empty space beside the logo
                     from swallowing clicks meant for the page. --}}
                <div class="pointer-events-none absolute left-4 top-4 z-30 sm:left-6 sm:top-6">
                    <a href="{{ url('/') }}" class="pointer-events-auto inline-block">
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ config('app.name', 'ScholarNest') }}"
                            class="h-20 w-20 select-none rounded-[10px] object-contain"
                        >
                    </a>
                </div>
            @endunless

            @if ($header)
            <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    @if ($school)
                        {{-- This school, named and badged. Not a link: there is
                             nowhere on the platform for a parent with no
                             account to be sent. --}}
                        <div class="flex min-w-0 items-center gap-2.5">
                            @if ($schoolLogoUrl)
                                <img
                                    src="{{ $schoolLogoUrl }}"
                                    alt="{{ $school->name }}"
                                    class="h-9 w-9 shrink-0 rounded-[5px] object-contain lg:rounded-[10px]"
                                >
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[5px] bg-primary-100 text-sm font-extrabold text-primary-700 lg:rounded-[10px]">
                                    {{ \Illuminate\Support\Str::of($school->name)->substr(0, 1)->upper() }}
                                </span>
                            @endif
                            <span class="truncate text-lg font-bold text-gray-900">{{ $school->name }}</span>
                        </div>
                    @elseif ($superAdminAccess)
                        {{-- The logo mark is the click target, so it cannot
                             navigate - the first click would leave the page and
                             the sequence could never reach five. The wordmark
                             beside it keeps the link home, so nothing is lost.

                             Nothing here announces itself: no title, no cursor
                             change, no hint in the markup. --}}
                        <div class="flex items-center gap-2">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ config('app.name', 'ScholarNest') }}"
                                @click="registerClick()"
                                class="h-20 w-20 shrink-0 select-none rounded-[5px] shadow-md shadow-primary-500/30 lg:rounded-[10px]"
                            >
                            <a href="{{ url('/') }}" class="text-lg font-bold text-gray-900">Scholar<span class="text-primary-500">Nest</span></a>
                        </div>
                    @else
                        <a href="{{ url('/') }}" class="flex items-center gap-2">
                            <img
                                src="{{ $logoUrl }}"
                                alt="{{ config('app.name', 'ScholarNest') }}"
                                class="h-20 w-20 shrink-0 rounded-[5px] shadow-md shadow-primary-500/30 lg:rounded-[10px]"
                            >
                            <span class="text-lg font-bold text-gray-900">Scholar<span class="text-primary-500">Nest</span></span>
                        </a>
                    @endif

                    @if ($authQuestion)
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <span class="hidden sm:inline">{{ $authQuestion }}</span>
                            <a
                                href="{{ $authLinkRoute }}"
                                class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                            >
                                {{ $authLinkLabel }}
                            </a>
                        </div>
                    @endif
                </div>
            </header>
            @endif

            <main class="relative flex-1 overflow-hidden bg-white">
                @if ($background)
                    <div class="pointer-events-none absolute inset-0">
                        @if ($background->type->value === 'video')
                            <video src="{{ $background->url() }}" autoplay muted loop playsinline class="h-full w-full object-cover"></video>
                        @else
                            <img src="{{ $background->url() }}" alt="" class="h-full w-full object-cover">
                        @endif
                        <div class="absolute inset-0 bg-white/75"></div>
                    </div>
                @endif

                {{-- Two large blurred blue discs used to sit here, one top
                     right and one bottom left. They were not a gradient in
                     the CSS sense - which is why searching for "gradient"
                     found nothing - but a 384px circle at blur-3xl reads as
                     one, and they tinted every portal, every sign-in and the
                     school finder pale blue. The background is plain white. --}}

                @if ($simple)
                    <div class="relative mx-auto flex min-h-[60vh] w-full max-w-md flex-col items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                @else
                    <div class="relative mx-auto grid w-full max-w-7xl grid-cols-1 gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-12 lg:px-8 lg:py-16">
                        <div>{{ $left }}</div>
                        <div>{{ $slot }}</div>
                    </div>
                @endif
            </main>

            <footer class="border-t border-gray-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-sm text-gray-500 sm:flex-row sm:px-6 lg:px-8">
                    <span>&copy; {{ now()->year }} ScholarNest. All rights reserved.</span>
                    <div class="flex items-center gap-4">
                        <a href="#" class="hover:text-primary-500">Privacy Policy</a>
                        <a href="#" class="hover:text-primary-500">Terms of Service</a>
                        <a href="#" class="hover:text-primary-500">Help Center</a>
                    </div>
                </div>
            </footer>

            @if ($superAdminAccess)
                <x-super-admin-login-dialog />
            @endif
        </div>
    </body>
</html>
