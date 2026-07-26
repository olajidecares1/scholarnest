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

        <title>{{ $pageTitle }} - {{ config('app.name', 'EduNest') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full transform flex-col bg-primary-600 text-white transition-transform duration-200 lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center gap-2 px-5 py-5">
                <img
                    src="{{ asset('images/logo-icon-dark.png') }}"
                    alt="EduNest"
                    class="h-9 w-9 shrink-0 rounded-[5px] bg-white/10 lg:rounded-[10px]"
                >
                <div>
                    <p class="text-lg font-bold leading-tight">EduNest</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-100">School Dashboard</p>
                </div>
            </div>

            <nav class="mt-4 flex-1 space-y-1 overflow-y-auto px-3">
                <a
                    href="{{ route('dashboard') }}"
                    class="flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition lg:rounded-[10px] {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white' : 'text-primary-100 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 11.5L12 4l8 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M6 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Dashboard
                </a>

                <a
                    href="{{ route('subscriptions.choose-plan') }}"
                    class="flex min-h-[44px] items-center gap-3 rounded-[5px] px-3 py-2.5 text-sm font-medium transition lg:rounded-[10px] {{ request()->routeIs('subscriptions.*') ? 'bg-white/15 text-white' : 'text-primary-100 hover:bg-white/10 hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.75" />
                        <path d="M3 10h18" stroke="currentColor" stroke-width="1.75" />
                    </svg>
                    Subscriptions
                </a>
            </nav>

            <div class="m-3 rounded-[5px] bg-white/10 p-4 text-center lg:rounded-[10px]">
                <p class="text-sm font-semibold">Need Help?</p>
                <p class="mt-1 text-xs text-primary-100">Our support team is here to help you anytime.</p>
                <a
                    href="#"
                    class="mt-3 inline-flex w-full items-center justify-center rounded-[5px] bg-white px-3 py-2 text-xs font-semibold text-primary-600 lg:rounded-[10px]"
                >
                    Contact Support
                </a>
            </div>

            <p class="px-5 pb-5 text-xs text-primary-200">&copy; {{ now()->year }} EduNest. All rights reserved.</p>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-gray-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 lg:hidden"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold text-gray-900">{{ $pageTitle }}</h1>
                    @if ($pageSubtitle)
                        <p class="truncate text-sm text-gray-500">{{ $pageSubtitle }}</p>
                    @endif
                </div>

                <div class="relative hidden md:block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        disabled
                        placeholder="Search schools, users, payments..."
                        class="w-64 rounded-[5px] border border-gray-200 bg-gray-50 py-2 pl-9 pr-3 text-sm text-gray-500 placeholder:text-gray-400 lg:rounded-[10px]"
                    >
                </div>

                <button type="button" class="relative flex h-10 w-10 items-center justify-center rounded-[5px] text-gray-500 hover:bg-gray-100 lg:rounded-[10px]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3a5 5 0 00-5 5v3.2c0 .5-.2 1-.5 1.4L5 15h14l-1.5-2.4c-.3-.4-.5-.9-.5-1.4V8a5 5 0 00-5-5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold text-gray-900">{{ auth()->user()->name }}</span>
                            <span class="block text-xs text-gray-500">{{ auth()->user()->role->label() }}</span>
                        </span>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        style="display: none;"
                        class="absolute right-0 mt-2 w-48 rounded-[5px] border border-gray-200 bg-white py-1 shadow-lg lg:rounded-[10px]"
                    >
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
