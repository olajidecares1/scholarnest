{{-- A standalone page, deliberately.

     Rendering this inside the dashboard chrome would put the sidebar's link to
     the very feature being refused right beside the refusal. It is its own
     page, centred, with its own way back - which is also why it does not
     depend on any of the layouts' data being present. --}}
@props(['title'])

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

        <title>{{ $title }} - {{ config('app.name', 'ScholarNest') }}</title>

        <x-favicon />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>

    <body class="min-h-dvh bg-[#F1F5FC] font-sans text-[#0F2A5C] antialiased">
        <div class="flex min-h-dvh flex-col">
            <header class="shrink-0 px-6 py-5 sm:px-10">
                <div class="flex items-center gap-2.5">
                    <img src="{{ $logoUrl }}" alt="" class="h-8 w-8 shrink-0 rounded-[8px]">
                    <span class="text-[19px] font-bold leading-none tracking-tight">ScholarNest</span>
                </div>
            </header>

            <main class="flex flex-1 items-center justify-center px-5 pb-10">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
