@props([
    'authQuestion' => null,
    'authLinkLabel' => null,
    'authLinkRoute' => null,
    'simple' => false,
    'background' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path) : asset('images/logo-icon-dark.png');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'EduNest') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        @if ($platformSettings->favicon_path)
            <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::url($platformSettings->favicon_path) }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>
    <body class="min-h-screen bg-white font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ config('app.name', 'EduNest') }}"
                            class="h-9 w-9 shrink-0 rounded-[5px] shadow-md shadow-primary-500/30 lg:rounded-[10px]"
                        >
                        <span class="text-lg font-bold text-gray-900">Edu<span class="text-primary-500">Nest</span></span>
                    </a>

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

            <main class="relative flex-1 overflow-hidden bg-gray-50">
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

                <div class="pointer-events-none absolute -top-24 right-0 h-96 w-96 rounded-full bg-primary-100/60 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 left-0 h-72 w-72 rounded-full bg-primary-50 blur-3xl"></div>

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
