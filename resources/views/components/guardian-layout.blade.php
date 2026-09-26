@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
    'activeChild' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $guardian = auth('guardian')->user();
    $school = $guardian->school;
    $activeChild ??= $guardian->students->first();

    // Bound to the child being viewed: a parent is not looking at "results"
    // but at this child's results, so switching child switches the whole menu
    // rather than leaving a link pointing at a sibling.
    $portalNav = \App\Support\PortalNavigation::forGuardian($guardian, $activeChild);

    $unreadNotifications = \App\Support\PortalNavigation::unreadNotifications($guardian);

    $portalBadges = array_filter([
        'guardian.notifications.index' => $unreadNotifications,
    ]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Using the page counts as activity: see resources/js/session-keep-alive.js. --}}
        <meta name="session-keep-alive" content="{{ route('session.keep-alive', absolute: false) }}">

        <title>{{ $pageTitle }} | {{ $school->name }}</title>

        <x-favicon :school="$school" />

        {{-- Installable: an icon on the home screen that opens THIS school. --}}
        <x-pwa :school="$school" portal="guardian" />
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
            @php
                $notifications = $guardian->notifications()->latest()->take(8)->get();
                $unreadCount = $guardian->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur print:hidden dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <small class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</small>
                    @endif
                </div>

                {{-- Messages sit beside the bell, as they would on a phone.
                     The bell keeps its dropdown; this is the envelope. --}}
                <x-portal-top-actions
                    :messages-url="\Illuminate\Support\Facades\Route::has('guardian.messages.index') ? route('guardian.messages.index', $school) : null"
                />

                @if (\Illuminate\Support\Facades\Route::has('guardian.notifications.index'))
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-9 w-9 items-center justify-center rounded-[6px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-blue-600 hover:shadow-sm active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800">
                            <i class="fa-solid fa-bell fa-fw text-[17px] leading-none"></i>
                            @if ($unreadCount > 0)
                                <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 animate-pulse items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ min($unreadCount, 99) }}</span>
                            @endif
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            style="display: none;"
                            class="absolute right-0 z-30 mt-2 w-80 rounded-[8px] border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Notifications</p>
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                @forelse ($notifications as $notification)
                                    <div class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 dark:border-gray-700/50">
                                        @if (is_null($notification->read_at))
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span>
                                        @else
                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-transparent"></span>
                                        @endif
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</small>
                                            <small class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</small>
                                        </span>
                                    </div>
                                @empty
                                    <small class="block px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</small>
                                @endforelse
                            </div>
                            <a href="{{ route('guardian.notifications.index', $school) }}" class="block border-t border-gray-100 px-4 py-2.5 text-center text-xs font-semibold text-blue-600 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">View All</a>
                        </div>
                    </div>
                @endif

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
                    <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-blue-700">{{ Str::of($guardian->name)->substr(0, 1)->upper() }}</span>
                </span>
            </header>

            @php
                // Prev/next walks the same menu as everything else, which is
                // already bound to the child being viewed, so a swipe cannot
                // wander onto a sibling's pages.
                $pageNavItems = collect([
                    ['route' => 'guardian.dashboard', 'label' => 'Home', 'url' => route('guardian.dashboard', $school)],
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

                <x-app-version class="mt-6 text-center" />
            </main>
        </div>

        {{-- The app-style bottom bar. Every item, and every plan and role
             rule behind it, comes from App\Support\PortalNavigation. --}}
        <x-portal-bottom-nav
            :primary="$portalNav['primary']"
            :categories="$portalNav['categories']"
            :badges="$portalBadges"
            :logout-url="route('guardian.logout', $school)"
        />

        <x-idle-session-guard :logout-url="route('guardian.logout', $school)" />
    </body>
</html>
