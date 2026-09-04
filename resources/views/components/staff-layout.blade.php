@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $staff = auth('staff')->user();
    $school = $staff->school;

    // One source for what this member of staff may see - the bottom bar, the
    // More sheet and the home screen all read it, so they cannot disagree.
    $portalNav = \App\Support\PortalNavigation::forStaff($staff);
    $portalBadges = [];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ $school->name }}</title>

        <x-favicon :school="$school" />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- The platform palette first, then this school's own brand colour
             over it. The sidebar icons read --color-primary, so School A
             gets School A's colour and a school that has set none falls
             back to the platform's rather than losing its icons. --}}
        <style>
            {!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}
            {!! $school?->website?->brand_primary_color ? \App\Support\BrandColorScale::cssVariables('primary', $school->website->brand_primary_color) : '' !!}
        </style>
    </head>
    <body class="overflow-x-hidden bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">

        {{-- The app shell. These portals are phone and tablet interfaces at
             every width: there is no desktop layout to fall through to, so on a
             wide screen the app is centred at a comfortable reading width
             rather than stretched across two feet of glass. --}}
        <div class="mx-auto w-full max-w-3xl">
            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="
                        dark = !dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.theme = dark ? 'dark' : 'light';
                    "
                    :class="dark ? 'bg-blue-600' : 'bg-gray-300'"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-300 ease-in-out active:scale-95 dark:bg-gray-600"
                    role="switch"
                    :aria-checked="dark.toString()"
                    title="Toggle dark mode"
                >
                    <span class="sr-only">Toggle dark mode</span>
                    <span :class="dark ? 'translate-x-5' : 'translate-x-0.5'" class="inline-flex h-5 w-5 transform items-center justify-center rounded-full bg-white shadow-md ring-0 transition-transform duration-300 ease-in-out"></span>
                </button>

                <span class="relative ml-2 hidden h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-blue-50 dark:border-gray-700 sm:flex">
                    @if ($staff->photoUrl())
                        <img src="{{ $staff->photoUrl() }}" class="h-full w-full object-cover">
                    @else
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-blue-700">{{ Str::of($staff->fullName())->substr(0, 1)->upper() }}</span>
                    @endif
                </span>
            </header>

            @php
                // Prev/next walks the same menu as everything else, so the
                // order a swipe follows is the order the home screen shows.
                $pageNavItems = collect([
                    ['route' => 'staff.dashboard', 'label' => 'Home', 'url' => route('staff.dashboard', $school)],
                ])
                    ->concat(collect($portalNav['categories'])->flatten(1)->map(
                        fn (array $item) => ['route' => $item['route'], 'label' => $item['label'], 'url' => $item['url']]
                    ))
                    ->values()
                    ->all();
                $pageNav = \App\Support\PageNavigator::resolve($pageNavItems, request()->route()?->getName());
            @endphp
            <x-mobile-page-nav :prev="$pageNav['prev']" :next="$pageNav['next']" :current-label="$pageNav['current']" always-visible />

            <main class="edn-portal-main p-3 sm:p-5 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>

        {{-- The app-style bottom bar. Every item, and every plan and role
             rule behind it, comes from App\Support\PortalNavigation. --}}
        <x-portal-bottom-nav
            :primary="$portalNav['primary']"
            :categories="$portalNav['categories']"
            :badges="$portalBadges"
            :logout-url="route('staff.logout', $school)"
        />

        <x-idle-session-guard :logout-url="route('staff.logout', $school)" />
    </body>
</html>
