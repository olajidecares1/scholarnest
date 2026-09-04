@props([
    'title' => null,
    'heading' => null,
    'summary' => null,
    'version' => null,
    'lastUpdated' => null,
])

@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path
        ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path)
        : asset('images/logo-icon-dark.png');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? 'Legal' }} &middot; {{ config('app.name', 'ScholarNest') }}</title>
        @if ($summary)
            <meta name="description" content="{{ \Illuminate\Support\Str::limit($summary, 160) }}">
        @endif

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>

    <body class="min-h-dvh bg-[#F1F5FC] font-sans text-[#0F2A5C] antialiased">
        <header class="border-b border-[#DBE4F3] bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
                <a href="{{ route('legal.index') }}" class="flex items-center gap-2.5">
                    <img src="{{ $logoUrl }}" alt="" class="h-7 w-7 rounded-[6px] object-contain">
                    <span class="text-[15px] font-extrabold tracking-tight">{{ config('app.name', 'ScholarNest') }}</span>
                </a>

                {{-- Back to registration, because that is where most people
                     arrive from. It opens in this tab and the form is still
                     filled in behind it - the link that brought them here
                     opened a new one. --}}
                <a href="{{ route('register') }}" class="text-[12.5px] font-semibold text-primary-600 hover:underline">
                    Register your school
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">
            @if ($heading)
                <div class="mb-8 border-b border-[#DBE4F3] pb-7">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-primary-600">
                        {{ config('app.name', 'ScholarNest') }} Legal
                    </p>
                    <h1 class="mt-2 text-[28px] font-extrabold leading-tight tracking-tight sm:text-[34px]">
                        {{ $heading }}
                    </h1>
                    @if ($summary)
                        <p class="mt-2.5 max-w-2xl text-[14.5px] leading-relaxed text-[#4A648F]">{{ $summary }}</p>
                    @endif
                    @if ($version || $lastUpdated)
                        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-[12px] text-[#6E85AC]">
                            @if ($version)
                                <span>Version <strong class="font-semibold text-[#3D5378]">{{ $version }}</strong></span>
                            @endif
                            @if ($lastUpdated)
                                <span>Last updated <strong class="font-semibold text-[#3D5378]">{{ $lastUpdated }}</strong></span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-[#DBE4F3] bg-white">
            <div class="mx-auto max-w-4xl px-5 py-6 sm:px-8">
                <p class="text-[12px] text-[#6E85AC]">
                    &copy; {{ now()->year }} {{ config('app.name', 'ScholarNest') }}.
                    <a href="{{ route('legal.index') }}" class="font-semibold text-primary-600 hover:underline">All legal documents</a>
                </p>
            </div>
        </footer>
    </body>
</html>
