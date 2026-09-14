@props(['school', 'title' => null, 'description' => null, 'image' => null, 'canonical' => null])

{{-- A school's Job Portal.

     SEPARATE FROM THE WEBSITE, deliberately: it needs no published website and
     carries only what an applicant needs, who the school is, where it is, how
     to reach it, and its vacancies. Most applicants arrive from a link shared
     on WhatsApp or Facebook, on a phone, so the page is built phone-first.

     BRANDING comes from the school: its logo, name and address from its
     profile, its colour from its website's brand colour when it has one and
     the platform theme otherwise. --}}
@php
    $contact = \App\Support\SchoolContact::for($school);
    $website = $school->website;
    $brand = $website?->brand_primary_color;
    $portalUrl = $school->publicUrl('public.jobs.index');
    $websiteUrl = $school->websiteUrl();
    $pageTitle = $title ? "{$title} · {$school->name} Job Portal" : "{$school->name} Job Portal";
    $pageDescription = $description ?: "Current vacancies at {$school->name}. View open positions and apply online.";
    $logoUrl = $school->logoUrl();
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ Str::limit($pageDescription, 160) }}">
        <x-favicon :school="$school" :platform-fallback="false" />

        {{-- Link previews. What WhatsApp, Facebook, X, LinkedIn and Telegram
             show when a vacancy's link is pasted: the job title and school,
             a short description, and the vacancy's preview image. --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $school->name }}">
        <meta property="og:title" content="{{ $title ? "{$title} at {$school->name}" : "{$school->name} Job Portal" }}">
        <meta property="og:description" content="{{ Str::limit($pageDescription, 200) }}">
        @if ($canonical)
            <meta property="og:url" content="{{ $canonical }}">
            <link rel="canonical" href="{{ $canonical }}">
        @endif
        @if ($image ?? $logoUrl)
            <meta property="og:image" content="{{ $image ?? $logoUrl }}">
            <meta name="twitter:image" content="{{ $image ?? $logoUrl }}">
        @endif
        @if ($image)
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endif
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title ? "{$title} at {$school->name}" : "{$school->name} Job Portal" }}">
        <meta name="twitter:description" content="{{ Str::limit($pageDescription, 200) }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                {!! $brand
                    ? \App\Support\BrandColorScale::cssVariables('primary', $brand)
                    : \App\Support\ThemePreset::cssVariables(\App\Models\Setting::current()->theme_preset) !!}
            }
        </style>
        {{ $head ?? '' }}
    </head>
    <body class="min-h-dvh bg-[#F4F7FC] font-sans text-[#0F2A5C] antialiased">
        <header class="border-b border-[#E1E8F2] bg-white">
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
                <a href="{{ $portalUrl }}" class="flex min-w-0 flex-1 items-center gap-3">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $school->name }} logo" class="h-11 w-11 shrink-0 rounded-[8px] object-contain sm:h-12 sm:w-12">
                    @else
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] bg-primary-100 text-lg font-extrabold text-primary-700 sm:h-12 sm:w-12">
                            {{ Str::of($school->name)->substr(0, 1)->upper() }}
                        </span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate text-[15px] font-extrabold leading-tight text-[#0F2A5C] sm:text-base">{{ $school->name }}</span>
                        <span class="block truncate text-[12px] font-semibold text-primary-600">
                            <i class="fa-solid fa-briefcase mr-1" aria-hidden="true"></i>Job Portal
                        </span>
                    </span>
                </a>

                @if ($websiteUrl)
                    <a href="{{ $websiteUrl }}" class="hidden shrink-0 items-center gap-1.5 rounded-[8px] border border-[#E1E8F2] px-3 py-2 text-[12.5px] font-semibold text-[#3D5378] hover:border-primary-300 hover:text-primary-700 sm:inline-flex">
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                        School website
                    </a>
                @endif
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-12 border-t border-[#E1E8F2] bg-white">
            <div class="mx-auto grid max-w-5xl gap-6 px-4 py-8 text-[13px] text-[#5B7099] sm:grid-cols-2 sm:px-6">
                <div class="min-w-0">
                    <p class="font-bold text-[#0F2A5C]">{{ $school->name }}</p>
                    @if ($contact->address)
                        <p class="mt-2 flex gap-2"><i class="fa-solid fa-location-dot mt-0.5 w-4 shrink-0 text-primary-500" aria-hidden="true"></i><span class="min-w-0 break-words">{{ $contact->address }}</span></p>
                    @endif
                    @if ($contact->phone)
                        <p class="mt-1.5 flex gap-2"><i class="fa-solid fa-phone mt-0.5 w-4 shrink-0 text-primary-500" aria-hidden="true"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="hover:text-primary-700">{{ $contact->phone }}</a></p>
                    @endif
                    @if ($contact->email)
                        <p class="mt-1.5 flex gap-2"><i class="fa-solid fa-envelope mt-0.5 w-4 shrink-0 text-primary-500" aria-hidden="true"></i><a href="mailto:{{ $contact->email }}" class="min-w-0 break-all hover:text-primary-700">{{ $contact->email }}</a></p>
                    @endif
                </div>
                <div class="sm:text-right">
                    <a href="{{ $portalUrl }}" class="inline-flex items-center gap-1.5 font-semibold text-primary-700 hover:text-primary-800">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        All vacancies
                    </a>
                    @if ($websiteUrl)
                        <br class="sm:hidden">
                        <a href="{{ $websiteUrl }}" class="mt-2 inline-flex items-center gap-1.5 font-semibold text-primary-700 hover:text-primary-800 sm:ml-4 sm:mt-0">
                            <i class="fa-solid fa-globe" aria-hidden="true"></i>
                            School website
                        </a>
                    @endif
                    <p class="mt-4 text-[11.5px] text-[#8194B3]">Recruitment powered by AkademicNest</p>
                </div>
            </div>
        </footer>
    </body>
</html>
