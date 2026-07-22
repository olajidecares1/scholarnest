@props([
    'authQuestion',
    'authLinkLabel',
    'authLinkRoute',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'EduNest') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-[5px] bg-primary-500 text-white lg:rounded-[10px]">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3L2 8L12 13L22 8L12 3Z" fill="currentColor" />
                                <path d="M6 10.5V16C6 16 8 19 12 19C16 19 18 16 18 16V10.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M22 8V14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                            </svg>
                        </span>
                        <span class="text-lg font-bold text-gray-900">Edu<span class="text-primary-500">Nest</span></span>
                    </a>

                    @if ($authQuestion)
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <span class="hidden sm:inline">{{ $authQuestion }}</span>
                            <a
                                href="{{ $authLinkRoute }}"
                                class="rounded-[5px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-600 lg:rounded-[10px]"
                            >
                                {{ $authLinkLabel }}
                            </a>
                        </div>
                    @endif
                </div>
            </header>

            <main class="mx-auto grid w-full max-w-7xl flex-1 grid-cols-1 gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-12 lg:px-8 lg:py-16">
                <div>{{ $left }}</div>
                <div>{{ $slot }}</div>
            </main>

            <footer class="border-t border-gray-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-sm text-gray-500 sm:flex-row sm:px-6 lg:px-8">
                    <span>&copy; {{ now()->year }} EduNest. All rights reserved.</span>
                    <div class="flex items-center gap-4">
                        <a href="#" class="hover:text-primary-500">Privacy Policy</a>
                        <a href="#" class="hover:text-primary-500">Terms of Service</a>
                        <a href="#" class="hover:text-primary-500">Help Center</a>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
