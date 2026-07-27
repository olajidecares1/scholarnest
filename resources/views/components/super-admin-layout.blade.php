@props([
    'pageTitle' => 'Dashboard',
    'pageSubtitle' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - {{ config('app.name', 'EduNest') }} Super Admin</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <script>
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col overflow-y-auto bg-primary-800 text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-2 px-5 py-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[5px] bg-white p-1.5 shadow-sm transition-transform duration-300 ease-out hover:scale-105 hover:rotate-3 lg:rounded-[10px]">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="EduNest" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-lg font-bold leading-tight">EduNest</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-100">Super Admin</p>
                </div>
            </div>

            <nav class="mt-2 flex-1 space-y-1 px-3 pb-4">
                @php
                    $navLinkClasses = fn (bool $isActive) => 'group flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition-all duration-300 ease-out lg:rounded-[10px] '
                        .($isActive
                            ? 'bg-white/20 text-white shadow-sm'
                            : 'text-primary-50 hover:translate-x-1 hover:bg-white/10 hover:text-white');
                    $navIconClasses = 'h-5 w-5 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110';
                @endphp

                <a href="{{ route('super-admin.dashboard') }}" class="{{ $navLinkClasses(request()->routeIs('super-admin.dashboard')) }}">
                    <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 11.5L12 4l9 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5.5 10v8.5a1.5 1.5 0 001.5 1.5h3v-5.5a2 2 0 012-2h0a2 2 0 012 2V20h3a1.5 1.5 0 001.5-1.5V10" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="8.2" r="0.9" fill="currentColor" />
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('super-admin.schools.index') }}" class="{{ $navLinkClasses(request()->routeIs('super-admin.schools.*')) }}">
                    <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3l8 3.6v2L12 12 4 8.6v-2L12 3z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                        <path d="M4 8.6V16l8 4 8-4V8.6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M12 12v8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                    Schools
                </a>

                <div x-data="{ open: {{ request()->routeIs('super-admin.subscriptions.*') ? 'true' : 'false' }} }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="{{ $navLinkClasses(request()->routeIs('super-admin.subscriptions.*')) }} w-full"
                    >
                        <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" stroke="currentColor" stroke-width="1.75" />
                            <path d="M3.5 9.5h17" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                            <path d="M7 13.5l2.2 2.2L14 11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="flex-1 text-left">Subscriptions</span>
                        <svg
                            class="h-4 w-4 shrink-0 transition-transform duration-300 ease-out"
                            :class="{ 'rotate-180': open }"
                            viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
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
                            ['tab' => 'pending', 'label' => 'Pending Approvals', 'icon' => 'M12 7v5l3.2 1.9', 'extra' => '<circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />'],
                            ['tab' => 'active', 'label' => 'Active Subscriptions', 'icon' => 'M8 12.3l2.6 2.6L16.3 9', 'extra' => '<circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />'],
                            ['tab' => 'expired', 'label' => 'Expired Subscriptions', 'icon' => 'M9 9l6 6M15 9l-6 6', 'extra' => '<circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />'],
                            ['tab' => 'all', 'label' => 'All Subscriptions', 'icon' => 'M5 7h14M5 12h14M5 17h9', 'extra' => ''],
                        ] as $sub)
                            @php
                                $isActive = $sub['tab'] === 'pending'
                                    ? request()->routeIs('super-admin.subscriptions.index') && request('tab', 'pending') === 'pending'
                                    : request('tab') === $sub['tab'];
                            @endphp
                            <a
                                href="{{ route('super-admin.subscriptions.index', $sub['tab'] === 'pending' ? [] : ['tab' => $sub['tab']]) }}"
                                class="group flex items-center gap-2 rounded-[5px] px-3 py-2 text-sm transition-all duration-300 ease-out hover:translate-x-1 {{ $isActive ? 'font-semibold text-white' : 'text-primary-50 hover:text-white' }}"
                            >
                                <svg class="h-4 w-4 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    {!! $sub['extra'] !!}
                                    <path d="{{ $sub['icon'] }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ $sub['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @foreach ([
                    ['route' => 'super-admin.payments.index', 'label' => 'Payments', 'icon' => 'M12 4v2.2M12 17.8V20M8.5 8.5c0-1.4 1.6-2.5 3.5-2.5s3.5 1.1 3.5 2.3c0 3.2-7 1.4-7 4.7 0 1.3 1.6 2.3 3.5 2.3s3.5-1.1 3.5-2.5', 'circle' => true],
                    ['route' => 'super-admin.users.index', 'label' => 'Users', 'icon' => 'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7', 'circle' => false, 'extra' => '<circle cx="10.5" cy="9" r="3.25" stroke="currentColor" stroke-width="1.75" />'],
                    ['route' => 'super-admin.roles.index', 'label' => 'Roles & Permissions', 'icon' => 'M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z', 'extra' => '<path d="M9.2 12l1.9 1.9L15 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'super-admin.reports.index', 'label' => 'Reports', 'icon' => 'M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z', 'extra' => '<path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                    ['route' => 'super-admin.analytics.index', 'label' => 'Analytics', 'icon' => 'M4 19.5h16', 'extra' => '<path d="M6.5 19.5v-5.5M11 19.5V8M15.5 19.5v-8.7M20 19.5V5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />'],
                    ['route' => 'super-admin.communications.index', 'label' => 'Communications', 'icon' => 'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z', 'extra' => '<path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />'],
                    ['route' => 'super-admin.support-tickets.index', 'label' => 'Support Tickets', 'icon' => 'M4.5 8.5a2 2 0 012-2h11a2 2 0 012 2v7a2 2 0 01-2 2h-11a2 2 0 01-2-2v-7z', 'extra' => '<path d="M4.5 9.5l7.1 4.6a1 1 0 001.1 0l6.8-4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />'],
                    ['route' => 'super-admin.cms.index', 'label' => 'CMS', 'icon' => 'M4.5 6a1.5 1.5 0 011.5-1.5h6l2 2h5.5A1.5 1.5 0 0121 8v9.5A1.5 1.5 0 0119.5 19h-15A1.5 1.5 0 013 17.5v-11z', 'extra' => ''],
                    ['route' => 'super-admin.themes.index', 'label' => 'Themes', 'icon' => 'M12 3a9 9 0 109 9', 'extra' => '<circle cx="7.8" cy="10.5" r="1.1" fill="currentColor" /><circle cx="10.5" cy="6.8" r="1.1" fill="currentColor" /><circle cx="15.2" cy="7.3" r="1.1" fill="currentColor" /><circle cx="17.2" cy="12.5" r="1.1" fill="currentColor" />'],
                    ['route' => 'super-admin.settings.index', 'label' => 'System Settings', 'icon' => 'M10.3 3.3a2 2 0 013.4 0l.5.9a2 2 0 001.6 1l1-.1a2 2 0 012.1 2.1l-.1 1a2 2 0 001 1.6l.9.5a2 2 0 010 3.4l-.9.5a2 2 0 00-1 1.6l.1 1a2 2 0 01-2.1 2.1l-1-.1a2 2 0 00-1.6 1l-.5.9a2 2 0 01-3.4 0l-.5-.9a2 2 0 00-1.6-1l-1 .1a2 2 0 01-2.1-2.1l.1-1a2 2 0 00-1-1.6l-.9-.5a2 2 0 010-3.4l.9-.5a2 2 0 001-1.6l-.1-1a2 2 0 012.1-2.1l1 .1a2 2 0 001.6-1z', 'extra' => '<circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" />'],
                    ['route' => 'super-admin.audit-logs.index', 'label' => 'Audit Logs', 'icon' => 'M7 3.5h7l4 4v13a.5.5 0 01-.5.5h-11a.5.5 0 01-.5-.5v-16a.5.5 0 01.5-.5z', 'extra' => '<path d="M14 3.5V7.5h4M9 12.5h6M9 15.5h6M9 9.5h2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />'],
                ] as $item)
                    @php $isActive = request()->routeIs(str($item['route'])->beforeLast('.').'.*'); @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $navLinkClasses($isActive) }}">
                        <svg class="{{ $navIconClasses }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            @if (! empty($item['circle']))
                                <circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />
                            @endif
                            {!! $item['extra'] ?? '' !!}
                            <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="m-3 rounded-[5px] bg-white/10 p-4 text-center transition-colors duration-300 hover:bg-white/[0.15] lg:rounded-[10px]">
                <p class="text-sm font-semibold">Super Administrator</p>
                <p class="mt-1 text-xs text-primary-50">You have full access to all platform features.</p>
                <a
                    href="{{ route('super-admin.settings.index') }}"
                    class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-[5px] bg-white px-3 py-2 text-xs font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]"
                >
                    System Settings
                </a>
            </div>

            <p class="px-5 pb-5 text-xs text-primary-100">&copy; {{ now()->year }} EduNest. All rights reserved.</p>
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
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold text-gray-900 dark:text-white">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <p
                    x-data="{ time: '' }"
                    x-init="
                        const tick = () => time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        tick();
                        setInterval(tick, 1000 * 30);
                    "
                    x-text="time"
                    class="hidden shrink-0 text-sm font-medium tabular-nums text-gray-500 dark:text-gray-400 lg:block"
                ></p>

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
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.300ms="search()"
                        @focus="if (query.length >= 2) open = true"
                        @click.outside="open = false"
                        placeholder="Search schools, users, payments..."
                        autocomplete="off"
                        class="w-64 rounded-[5px] border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-700 placeholder:text-gray-400 transition-all duration-300 ease-out focus:border-primary-500 focus:bg-white focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:placeholder:text-gray-500 lg:rounded-[10px]"
                    >

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[5px] border border-gray-200 bg-white py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <template x-if="!loading && results.schools.length === 0 && results.users.length === 0 && results.subscriptions.length === 0">
                            <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No results found.</p>
                        </template>

                        <template x-if="results.schools.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Schools</p>
                                <template x-for="item in results.schools" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.users.length > 0">
                            <div class="px-2 pb-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Users</p>
                                <template x-for="item in results.users" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <template x-if="results.subscriptions.length > 0">
                            <div class="px-2">
                                <p class="px-2 py-1 text-xs font-bold uppercase text-gray-400">Subscriptions</p>
                                <template x-for="item in results.subscriptions" :key="item.url">
                                    <a :href="item.url" class="block rounded-[5px] px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <span class="font-medium text-gray-900 dark:text-white" x-text="item.title"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.subtitle"></span>
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
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]"
                >
                    <svg class="h-5 w-5 dark:hidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.75" />
                    </svg>
                    <svg class="hidden h-5 w-5 dark:block" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                    </svg>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="relative flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
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
                        class="absolute right-0 z-30 mt-2 w-80 rounded-[5px] border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">Notifications</p>
                            @if ($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-primary-500 hover:text-primary-600">Mark all read</button>
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
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</span>
                                        <span class="block text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <a
                    href="{{ route('super-admin.communications.index') }}"
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-800 lg:rounded-[10px]"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        <path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </a>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="group flex items-center gap-2 rounded-[5px] px-1.5 py-1 transition-colors duration-300 hover:bg-gray-100 dark:hover:bg-gray-800 lg:rounded-[10px]">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 transition-transform duration-300 ease-out group-hover:scale-105">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ auth()->user()->name }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->role->label() }}</span>
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
                        class="absolute right-0 mt-2 w-48 rounded-[5px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Log Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8 dark:bg-gray-900">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
