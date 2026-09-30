@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logoUrl('images/logo-mark.png');
    $canManageCbt = auth()->user()->hasPermission('manage_cbt');
    $cbtExamBodies = $canManageCbt ? \App\Models\CbtExamBody::orderBy('name')->get() : collect();
    $canManageResultPins = auth()->user()->hasPermission('manage_result_pins');

    /*
     * Every destination this account may open, gated once and read twice: by
     * the desktop rail below and by the mobile page-nav further down.
     */
    $sidebarItems = collect([
        ['route' => 'super-admin.dashboard', 'label' => 'Dashboard'],
        ['route' => 'super-admin.schools.index', 'label' => 'Schools'],
        ['route' => 'super-admin.subscriptions.index', 'label' => 'Subscriptions'],
    ])
        ->when($canManageCbt, fn ($c) => $c->push(['route' => 'super-admin.cbt.index', 'label' => 'CBT']))
        ->when($canManageCbt, fn ($c) => $c->push(['route' => 'super-admin.cbt.uploads.index', 'label' => 'Upload CBT']))
        ->when($canManageResultPins, fn ($c) => $c->push(['route' => 'super-admin.result-pins.index', 'label' => 'Result PINs']))
        ->concat([
            ['route' => 'super-admin.payments.index', 'label' => 'Payments'],
            ['route' => 'super-admin.payment-settings.index', 'label' => 'Payment Settings'],
            ['route' => 'super-admin.document-templates.index', 'label' => 'Templates'],
            ['route' => 'super-admin.users.index', 'label' => 'Users'],
            ['route' => 'super-admin.roles.index', 'label' => 'Roles & Permissions'],
            ['route' => 'super-admin.reports.index', 'label' => 'Reports'],
            ['route' => 'super-admin.analytics.index', 'label' => 'Analytics'],
            ['route' => 'super-admin.communications.index', 'label' => 'Communications'],
            ['route' => 'super-admin.support-tickets.index', 'label' => 'Support Tickets'],
            ['route' => 'super-admin.cms.index', 'label' => 'CMS'],
            ['route' => 'super-admin.legal.index', 'label' => 'Legal Documents'],
            ['route' => 'super-admin.media.index', 'label' => 'Media'],
            ['route' => 'super-admin.themes.index', 'label' => 'Themes'],
            ['route' => 'super-admin.settings.index', 'label' => 'System Settings'],
            ['route' => 'super-admin.audit-logs.index', 'label' => 'Audit Logs'],
        ])
        ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']))
        ->values();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} | {{ config('app.name', 'AkademicNest') }} Team</title>

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col overflow-y-auto bg-primary-800 text-white shadow-xl transition-transform duration-200 lg:hidden"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-2 px-5 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-white p-1.5 shadow-sm transition-transform duration-300 ease-out hover:scale-105 hover:rotate-3">
                    <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'AkademicNest') }}" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-lg font-bold leading-tight">AkademicNest</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-100">AkademicNest Team</p>
                </div>
            </div>

            <nav class="mt-2 flex-1 space-y-1 px-3 pb-4">
                @php
                    $navLinkClasses = fn (bool $isActive) => 'group flex min-h-[44px] items-center gap-3 rounded-[8px] px-3 py-2.5 text-sm font-medium transition-all duration-300 ease-out '
                        .($isActive
                            ? 'bg-white/20 text-white shadow-sm'
                            : 'text-primary-50 hover:translate-x-1 hover:bg-white/10 hover:text-white');
                    $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';
                @endphp

                <a href="{{ route('super-admin.dashboard') }}" class="{{ $navLinkClasses(request()->routeIs('super-admin.dashboard')) }}">
                    <i class="{{ \App\Support\SidebarMeta::icon('super-admin.dashboard') }} fa-fw {{ $navIconClasses }} text-[16px] leading-none" aria-hidden="true"></i>
                    Dashboard
                </a>

                <a href="{{ route('super-admin.schools.index') }}" class="{{ $navLinkClasses(request()->routeIs('super-admin.schools.*')) }}">
                    <i class="{{ \App\Support\SidebarMeta::icon('super-admin.schools.index') }} fa-fw {{ $navIconClasses }} text-[16px] leading-none" aria-hidden="true"></i>
                    Schools
                </a>

                @if ($canManageResultPins)
                    <a href="{{ route('super-admin.result-pins.index') }}" class="{{ $navLinkClasses(request()->routeIs('super-admin.result-pins.*')) }}">
                        <i class="{{ \App\Support\SidebarMeta::icon('super-admin.result-pins.index') }} fa-fw {{ $navIconClasses }} text-[16px] leading-none" aria-hidden="true"></i>
                        Result PINs
                    </a>
                @endif

                <div x-data="{ open: {{ request()->routeIs('super-admin.subscriptions.*') ? 'true' : 'false' }} }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="{{ $navLinkClasses(request()->routeIs('super-admin.subscriptions.*')) }} w-full"
                    >
                        <i class="fa-solid fa-credit-card {{ $navIconClasses }} text-[14px] leading-none" aria-hidden="true"></i>
                        <span class="flex-1 text-left">Subscriptions</span>
                        <i :class="{ 'rotate-180': open }" class="fa-solid fa-chevron-down shrink-0 transition-transform duration-300 ease-out text-[14px] leading-none" aria-hidden="true"></i>
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
                        class="mt-1 space-y-1 pl-8"
                    >
                        @foreach ([
                            ['tab' => 'pending', 'label' => 'Pending Approvals', 'icon' => 'fa-solid fa-hourglass-half'],
                            ['tab' => 'active', 'label' => 'Active Subscriptions', 'icon' => 'fa-solid fa-circle-check'],
                            ['tab' => 'expired', 'label' => 'Expired Subscriptions', 'icon' => 'fa-solid fa-circle-xmark'],
                            ['tab' => 'all', 'label' => 'All Subscriptions', 'icon' => 'fa-solid fa-list'],
                        ] as $sub)
                            @php
                                $isActive = $sub['tab'] === 'pending'
                                    ? request()->routeIs('super-admin.subscriptions.index') && request('tab', 'pending') === 'pending'
                                    : request('tab') === $sub['tab'];
                            @endphp
                            <a
                                href="{{ route('super-admin.subscriptions.index', $sub['tab'] === 'pending' ? [] : ['tab' => $sub['tab']]) }}"
                                class="group flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm transition-all duration-300 ease-out hover:translate-x-1 {{ $isActive ? 'font-semibold text-white' : 'text-primary-50 hover:text-white' }}"
                            >
                                <i class="{{ $sub['icon'] }} fa-fw shrink-0 text-[13px] leading-none transition-transform duration-300 ease-out group-hover:scale-110" aria-hidden="true"></i>
                                {{ $sub['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($canManageCbt)
                    <div x-data="{ open: {{ request()->routeIs('super-admin.cbt.*') ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="{{ $navLinkClasses(request()->routeIs('super-admin.cbt.*')) }} w-full"
                        >
                            <i class="fa-solid fa-note-sticky {{ $navIconClasses }} text-[14px] leading-none" aria-hidden="true"></i>
                            <span class="flex-1 text-left">CBT</span>
                            <i :class="{ 'rotate-180': open }" class="fa-solid fa-chevron-down shrink-0 transition-transform duration-300 ease-out text-[14px] leading-none" aria-hidden="true"></i>
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
                            class="mt-1 space-y-1 pl-8"
                        >
                            @foreach ($cbtExamBodies as $examBody)
                                <a
                                    href="{{ route('super-admin.cbt.exam-bodies.show', $examBody) }}"
                                    class="group flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm transition-all duration-300 ease-out hover:translate-x-1 {{ request()->route('examBody')?->is($examBody) ? 'font-semibold text-white' : 'text-primary-50 hover:text-white' }}"
                                >
                                    <i class="fa-solid fa-graduation-cap fa-fw shrink-0 text-[13px] leading-none transition-transform duration-300 ease-out group-hover:scale-110" aria-hidden="true"></i>
                                    {{ $examBody->name }} CBT
                                </a>
                            @endforeach
                            {{-- The uploader had a page, a queue, an extractor
                                 and no way in from here: it was reachable only
                                 from the All Exam Bodies landing page, so
                                 anybody working inside a particular exam body
                                 could not find it at all. --}}
                            <a
                                href="{{ route('super-admin.cbt.uploads.index') }}"
                                class="group flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm transition-all duration-300 ease-out hover:translate-x-1 {{ request()->routeIs('super-admin.cbt.uploads.*') ? 'font-semibold text-white' : 'text-primary-50 hover:text-white' }}"
                            >
                                <i class="{{ \App\Support\SidebarMeta::icon('super-admin.cbt.uploads.index') }} fa-fw shrink-0 text-[13px] leading-none transition-transform duration-300 ease-out group-hover:scale-110" aria-hidden="true"></i>
                                Upload CBT Document
                            </a>

                            <a
                                href="{{ route('super-admin.cbt.index') }}"
                                class="group flex items-center gap-2 rounded-[8px] px-3 py-2 text-sm transition-all duration-300 ease-out hover:translate-x-1 {{ request()->routeIs('super-admin.cbt.index') ? 'font-semibold text-white' : 'text-primary-50 hover:text-white' }}"
                            >
                                <i class="fa-solid fa-layer-group fa-fw shrink-0 text-[13px] leading-none transition-transform duration-300 ease-out group-hover:scale-110" aria-hidden="true"></i>
                                All Exam Bodies
                            </a>
                        </div>
                    </div>
                @endif

                @foreach ([
                    ['route' => 'super-admin.payments.index', 'label' => 'Payments'],
                    ['route' => 'super-admin.users.index', 'label' => 'Users'],
                    ['route' => 'super-admin.roles.index', 'label' => 'Roles & Permissions'],
                    ['route' => 'super-admin.reports.index', 'label' => 'Reports'],
                    ['route' => 'super-admin.analytics.index', 'label' => 'Analytics'],
                    ['route' => 'super-admin.communications.index', 'label' => 'Communications'],
                    ['route' => 'super-admin.support-tickets.index', 'label' => 'Support Tickets'],
                    ['route' => 'super-admin.cms.index', 'label' => 'CMS'],
                    ['route' => 'super-admin.legal.index', 'label' => 'Legal Documents'],
                    ['route' => 'super-admin.media.index', 'label' => 'Media'],
                    ['route' => 'super-admin.themes.index', 'label' => 'Themes'],
                    ['route' => 'super-admin.payment-settings.index', 'label' => 'Payment Settings'],
                    ['route' => 'super-admin.settings.index', 'label' => 'System Settings'],
                    ['route' => 'super-admin.audit-logs.index', 'label' => 'Audit Logs'],
                ] as $item)
                    @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $navLinkClasses($isActive) }}">
                        <i class="{{ \App\Support\SidebarMeta::icon($item['route']) }} fa-fw {{ $navIconClasses }} text-[16px] leading-none" aria-hidden="true"></i>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="m-3 rounded-[8px] bg-white/10 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15]">
                <p class="text-sm font-semibold">AkademicNest Team</p>
                <p class="mt-1 text-xs text-primary-50">You have full access to all platform features.</p>
                <a
                    href="{{ route('super-admin.settings.index') }}"
                    class="btn mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-[8px] bg-white px-3 py-2 text-xs font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md"
                >
                    <i class="fa-solid fa-gear btn-icon" aria-hidden="true"></i>
                    System Settings
                </a>
            </div>

            <p class="px-5 pb-5 text-xs text-primary-100">&copy; {{ now()->year }} AkademicNest. All rights reserved.</p>
            <x-app-version class="-mt-4 px-5 pb-5 text-primary-100 dark:text-primary-100" />
        </aside>

        {{-- The desktop sidebar. White, Font Awesome, and permission-gated by
             exactly the $sidebarItems the mobile page-nav reads, a redesign
             must not become a second answer to "what may this person open?".

             The mobile drawer above is a separate element and is unchanged. --}}
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-gray-200 bg-white shadow-sm print:hidden lg:flex dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-2.5 px-4 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-white p-1.5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'AkademicNest') }}" class="h-full w-full object-contain">
                </span>
                <div class="min-w-0">
                    <h4 class="truncate text-sm font-bold leading-tight text-gray-900 dark:text-white">AkademicNest</h4>
                    <small class="block truncate text-[11px] font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-300">AkademicNest Team</small>
                </div>
            </div>

            <nav class="edn-sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                @foreach ($sidebarItems as $item)
                    @php
                        $isActive = request()->routeIs(\Illuminate\Support\Str::of($item['route'])->beforeLast('.').'.*');
                        $railClasses = 'group relative flex items-start gap-3 rounded-[10px] px-3 py-2.5 text-left transition-all duration-200 ease-out '
                            .($isActive
                                ? 'bg-primary-50 text-primary-800 shadow-sm ring-1 ring-primary-200 dark:bg-primary-900/40 dark:text-primary-100 dark:ring-primary-700'
                                : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white');
                    @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $railClasses }}">
                        <i class="{{ \App\Support\SidebarMeta::icon($item['route']) }} fa-fw text-[18px] leading-none text-primary-600 transition-transform duration-200 group-hover:scale-110 dark:text-primary-300"></i>
                        <span class="min-w-0 flex-1">
                            <h4 class="truncate text-[13px] font-semibold leading-tight">{{ $item['label'] }}</h4>
                            <small class="mt-0.5 block truncate text-[11px] font-normal leading-tight text-gray-500 dark:text-gray-400">{{ \App\Support\SidebarMeta::description($item['route']) }}</small>
                        </span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="lg:pl-64">
            @php
                $notifications = auth()->user()->notifications()->latest()->take(8)->get();
                $unreadCount = auth()->user()->unreadNotifications()->count();
            @endphp

            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="icon-btn icon-btn--neutral lg:hidden"
                    data-tooltip="Open menu"
                    aria-label="Open menu"
                >
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <small class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</small>
                    @endif
                </div>

                <small
                    x-data="{ time: '' }"
                    x-init="
                        const tick = () => time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        tick();
                        setInterval(tick, 1000 * 30);
                    "
                    x-text="time"
                    class="hidden shrink-0 text-sm font-medium tabular-nums text-gray-500 dark:text-gray-400 lg:block"
                ></small>

                <div class="relative hidden md:block" x-data="{
                    open: false,
                    query: '',
                    loading: false,
                    results: { schools: [], users: [], subscriptions: [] },
                    async search() {
                        if (this.query.length < 2) { this.results = { schools: [], users: [], subscriptions: [] }; this.open = false; return; }
                        this.loading = true;
                        const res = await fetch('{{ route('super-admin.search') }}?q=' + encodeURIComponent(this.query));
                        this.results = await res.json();
                        this.loading = false;
                        this.open = true;
                    },
                }">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-[14px] leading-none" aria-hidden="true"></i>
                    </span>
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.300ms="search()"
                        @focus="if (query.length >= 2) open = true"
                        @click.outside="open = false"
                        placeholder="Search schools, users, payments..."
                        autocomplete="off"
                        class="w-64 pl-9 pr-3 transition-all duration-300 ease-out"
                    >

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[8px] border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <template x-if="!loading && results.schools.length === 0 && results.users.length === 0 && results.subscriptions.length === 0">
                            <small class="block px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No results found.</small>
                        </template>

                        <template x-if="results.schools.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Schools</p>
                                <template x-for="item in results.schools" :key="item.url">
                                    <a :href="item.url" class="block rounded-[8px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <small class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></small>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.users.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Users</p>
                                <template x-for="item in results.users" :key="item.url">
                                    <a :href="item.url" class="block rounded-[8px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <small class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></small>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.subscriptions.length > 0">
                            <div class="px-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Subscriptions</p>
                                <template x-for="item in results.subscriptions" :key="item.url">
                                    <a :href="item.url" class="block rounded-[8px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <small class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></small>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <button
                    type="button"
                    x-data
                    @click="
                        document.documentElement.classList.toggle('dark');
                        localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                    "
                    class="icon-btn icon-btn--neutral"
                    data-tooltip="Toggle dark mode"
                    aria-label="Toggle dark mode"
                >
                    <i class="fa-solid fa-moon dark:hidden" aria-hidden="true"></i>
                    <i class="fa-solid fa-sun hidden dark:block" aria-hidden="true"></i>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="icon-btn icon-btn--neutral" data-tooltip="Notifications" aria-label="Notifications">
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        @if($unreadCount > 0)
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
                            @if ($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="icon-btn icon-btn--approve icon-btn--sm" data-tooltip="Mark all read" aria-label="Mark all read"><i class="fa-solid fa-check-double" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </div>

                        <div class="max-h-80 overflow-y-auto">
                            @forelse ($notifications as $notification)
                                <a
                                    href="{{ route('notifications.read', $notification) }}"
                                    class="flex items-start gap-2 border-b border-gray-50 px-4 py-3 text-left transition-colors duration-200 hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700"
                                >
                                    @if (is_null($notification->read_at))
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"></span>
                                    @else
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-transparent"></span>
                                    @endif
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                        <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</small>
                                        <small class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</small>
                                    </span>
                                </a>
                            @empty
                                <small class="block px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</small>
                            @endforelse
                        </div>
                    </div>
                </div>

                <a
                    href="{{ route('super-admin.communications.index') }}"
                    class="icon-btn icon-btn--neutral"
                    data-tooltip="Communications"
                    aria-label="Communications"
                >
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                </a>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="group flex items-center gap-2 rounded-[8px] px-1.5 py-1 transition-colors duration-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 transition-transform duration-300 ease-out group-hover:scale-105">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ auth()->user()->name }}</span>
                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</small>
                        </span>
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
                        class="absolute right-0 mt-2 w-48 rounded-[8px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Log Out</button>
                        </form>
                    </div>
                </div>
            </header>

            @php
                $pageNavItems = $sidebarItems->map(fn ($item) => [...$item, 'url' => route($item['route'])])->all();
                $pageNav = \App\Support\PageNavigator::resolve($pageNavItems, request()->route()?->getName());
            @endphp
            <x-mobile-page-nav :prev="$pageNav['prev']" :next="$pageNav['next']" :current-label="$pageNav['current']" />

            <main class="p-4 sm:p-6 lg:p-8 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
