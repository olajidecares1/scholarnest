@props(['school', 'website', 'title' => null, 'usedFonts' => []])

@php
    $homeUrl = route('public.school-website', $school);
    $navLinks = $school->navLinks->isNotEmpty()
        ? $school->navLinks->map(fn ($link) => ['label' => $link->label, 'url' => $link->url])->all()
        : [
            ['label' => 'Home', 'url' => $homeUrl],
            ['label' => 'About', 'url' => route('public.school-about.index', $school)],
            ['label' => 'Academics', 'url' => $homeUrl.'#academics'],
            ['label' => 'Admissions', 'url' => route('public.school-admissions.index', $school)],
            ['label' => 'News', 'url' => route('public.school-news.index', $school)],
            ['label' => 'Events', 'url' => route('public.school-events.index', $school)],
            ['label' => 'Facilities', 'url' => route('public.school-facilities.index', $school)],
            ['label' => 'Gallery', 'url' => route('public.school-gallery.index', $school)],
            ['label' => 'Contact', 'url' => route('public.school-contact.index', $school)],
        ];
    [$brandPrimary, $brandSecondary] = collect(explode(' ', $school->name, 2))->pad(2, null)->all();
    $ctaDelay = 80 + count($navLinks) * 55 + 100;
    $ctaUrl = $website->cta_url ?: '#contact';
    $navbarScrolledClass = $website->navbar_bg_color ? 'bg-[var(--edn-navbar-bg)] shadow-md backdrop-blur-md' : 'bg-white/95 shadow-md backdrop-blur-md';
    $navbarUnscrolledClass = $website->navbar_bg_color ? 'bg-[var(--edn-navbar-bg)] shadow-none backdrop-blur-sm' : 'bg-white/85 shadow-none backdrop-blur-sm';
    $footerBlocks = $school->websiteBlocksFor('footer')->groupBy('section');
    $allUsedFonts = array_merge_recursive($usedFonts, \App\Support\GoogleFonts::usedInBlocks($footerBlocks->flatten(1)));
    $metaDescription = $school->websiteBlocksFor('about')->firstWhere('section', 'body')['content'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? "{$title} · {$school->name}" : $school->name }}</title>
        {{-- An empty data URI (not an omitted tag) deliberately stops the browser's
        automatic /favicon.ico fallback request, which would otherwise silently
        resolve to EduNest's own favicon rather than this school's (or none). --}}
        <link rel="icon" href="{{ $school->faviconUrl() ?? 'data:,' }}">
        @if ($metaDescription)
            <meta name="description" content="{{ Str::limit($metaDescription, 160) }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if ($website->brand_primary_color || $website->brand_secondary_color || $website->navbar_bg_color)
            <style>
                :root {
                    {!! $website->brand_primary_color ? \App\Support\BrandColorScale::cssVariables('primary', $website->brand_primary_color) : '' !!}
                    {!! $website->brand_secondary_color ? \App\Support\BrandColorScale::cssVariables('secondary', $website->brand_secondary_color) : '' !!}
                    @if ($website->navbar_bg_color)
                        --edn-navbar-bg: {{ $website->navbar_bg_color }};
                    @endif
                }
            </style>
        @endif
        {!! \App\Support\GoogleFonts::linkTagFor($allUsedFonts) !!}
    </head>
    <body class="bg-white font-sans text-gray-900 antialiased" x-data="{ mobileNavOpen: false }">
        <div class="bg-primary-700 px-4 py-2 text-xs font-medium text-white sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <span class="hidden truncate sm:inline">{{ $website->topbar_announcement ?: "Welcome to {$school->name}" }}</span>
                <div class="flex w-full items-center justify-between gap-4 text-white/90 sm:w-auto sm:justify-end">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.5" /><path d="M3.5 9.5h17M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                        {{ $website->topbar_badge_text ?: 'Admissions Open '.($school->current_session ?? now()->year) }}
                    </span>
                    <a href="{{ $website->topbar_link_url ?: $school->publicUrl('portal.index') }}" class="flex items-center gap-1.5 transition-colors duration-200 hover:text-white">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8.5" r="3" stroke="currentColor" stroke-width="1.5" /><path d="M5.5 19c1-3.5 3.8-5.5 6.5-5.5s5.5 2 6.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                        {{ $website->topbar_link_text ?: 'School Portal' }}
                    </a>
                </div>
            </div>
        </div>

        <header
            x-data="{ scrolled: false }"
            x-init="scrolled = window.scrollY > 12; window.addEventListener('scroll', () => { scrolled = window.scrollY > 12 })"
            :class="scrolled ? '{{ $navbarScrolledClass }}' : '{{ $navbarUnscrolledClass }}'"
            class="sticky top-0 z-30 border-b border-gray-100 transition-[background-color,box-shadow] duration-300 ease-out"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 transition-[padding] duration-300 ease-out sm:px-6"
                :class="scrolled ? 'py-2.5' : 'py-4'"
            >
                <a href="{{ $homeUrl }}" class="edn-enter group flex shrink-0 items-center gap-3" style="animation-delay: 0ms">
                    @if ($school->logoUrl())
                        <img
                            src="{{ $school->logoUrl() }}"
                            alt="{{ $school->name }}"
                            class="h-14 w-14 shrink-0 rounded-[12px] object-cover shadow-sm transition-transform duration-300 ease-out group-hover:scale-105 sm:h-16 sm:w-16"
                        >
                    @else
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[12px] bg-gradient-to-br from-primary-500 to-primary-700 text-xl font-extrabold text-white shadow-sm transition-transform duration-300 ease-out group-hover:scale-105 sm:h-16 sm:w-16 sm:text-2xl">
                            {{ Str::of($school->name)->substr(0, 1)->upper() }}
                        </span>
                    @endif
                    <span class="min-w-0 leading-none">
                        <span class="block truncate text-xl font-extrabold tracking-tight text-gray-900 sm:text-2xl">{{ $brandPrimary }}</span>
                        @if ($brandSecondary)
                            <span class="mt-1 block truncate text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 sm:text-xs">{{ $brandSecondary }}</span>
                        @endif
                    </span>
                </a>

                <nav class="hidden flex-1 items-center justify-center gap-0.5 xl:flex">
                    @foreach ($navLinks as $index => $link)
                        @php $isActive = request()->url() === $link['url']; @endphp
                        <a
                            href="{{ $link['url'] }}"
                            class="edn-enter edn-nav-link shrink-0 rounded-[6px] px-2.5 py-2 text-[15px] font-semibold {{ $isActive ? 'is-active text-primary-600' : 'text-gray-700 hover:text-primary-600' }}"
                            style="animation-delay: {{ 80 + $index * 55 }}ms"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex shrink-0 items-center gap-2">
                    <a
                        href="{{ $ctaUrl }}"
                        class="edn-enter hidden items-center gap-1.5 rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-600/20 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:scale-[1.03] hover:bg-primary-700 hover:shadow-lg hover:shadow-primary-600/30 active:scale-[0.97] sm:flex"
                        style="animation-delay: {{ $ctaDelay }}ms"
                    >
                        {{ $website->cta_text ?: 'Apply Now' }}
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                    <button
                        type="button"
                        @click="mobileNavOpen = !mobileNavOpen"
                        class="edn-enter flex h-11 w-11 items-center justify-center rounded-[8px] text-gray-500 transition-all duration-200 ease-out hover:scale-105 hover:bg-gray-100 active:scale-95 xl:hidden"
                        style="animation-delay: {{ $ctaDelay }}ms"
                    >
                        <svg class="h-6 w-6 transition-transform duration-300 ease-out" :class="{ 'rotate-90': mobileNavOpen }" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path x-show="!mobileNavOpen" d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                            <path x-show="mobileNavOpen" style="display: none;" d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
            </div>

            <nav
                x-show="mobileNavOpen"
                style="display: none;"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="border-t border-gray-100 bg-white px-4 py-3 xl:hidden"
            >
                <div class="flex flex-col gap-1">
                    @foreach ($navLinks as $link)
                        @php $isActive = request()->url() === $link['url']; @endphp
                        <a
                            href="{{ $link['url'] }}"
                            class="rounded-[8px] px-3 py-2.5 text-[15px] font-semibold transition-colors duration-200 {{ $isActive ? 'bg-primary-50 text-primary-600' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    <a
                        href="{{ $ctaUrl }}"
                        class="mt-2 flex items-center justify-center gap-1.5 rounded-[8px] bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all duration-200 hover:bg-primary-700"
                    >
                        {{ $website->cta_text ?: 'Apply Now' }}
                    </a>
                </div>
            </nav>
        </header>

        <main>
            {{ $slot }}
        </main>

        @if ($footerBlocks->has('cta'))
            <section class="relative bg-primary-700 py-10">
                <div class="relative mx-auto max-w-7xl px-4 sm:px-6">
                    <x-website-blocks :blocks="$footerBlocks->get('cta')" height="90px" />
                </div>
            </section>
        @endif

        <footer id="contact" class="bg-[#0b1220] text-gray-300">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-5">
                <div class="sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-2">
                        @if ($school->logoUrl())
                            <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="h-9 w-9 rounded-[8px] object-cover">
                        @else
                            <span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-primary-600 text-sm font-extrabold text-white">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                        @endif
                        <span class="text-sm font-extrabold text-white">{{ $school->name }}</span>
                    </div>
                    <div class="relative mt-3">
                        <x-website-blocks :blocks="$footerBlocks->get('description', collect())" height="70px" />
                    </div>
                    <div class="mt-4 flex gap-3">
                        @if ($website->facebook_url)
                            <a href="{{ $website->facebook_url }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-gray-300 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V8c0-.9.25-1.5 1.55-1.5H16.7V3.7C16.4 3.65 15.4 3.55 14.25 3.55c-2.4 0-4.05 1.45-4.05 4.15v2.25H7.5v3.1h2.7v8h3.3z" /></svg>
                            </a>
                        @endif
                        @if ($website->twitter_url)
                            <a href="{{ $website->twitter_url }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-gray-300 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6.5c-.6.3-1.3.5-2 .6.7-.4 1.3-1.2 1.5-2-.7.4-1.5.7-2.3.9a3.5 3.5 0 00-6 3.2A10 10 0 014 5.9a3.5 3.5 0 001.1 4.7c-.5 0-1-.2-1.5-.4v.1c0 1.7 1.2 3.1 2.8 3.4-.5.1-1 .2-1.6.1.4 1.4 1.7 2.4 3.3 2.4A7 7 0 014 17.5a10 10 0 005.4 1.6c6.5 0 10-5.4 10-10v-.5c.7-.5 1.3-1.1 1.7-1.8z" /></svg>
                            </a>
                        @endif
                        @if ($website->instagram_url)
                            <a href="{{ $website->instagram_url }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-gray-300 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.6" /><circle cx="12" cy="12" r="3.7" stroke="currentColor" stroke-width="1.6" /><circle cx="17" cy="7" r="1" fill="currentColor" /></svg>
                            </a>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-white">Quick Links</h3>
                    <ul class="mt-3 space-y-2 text-xs">
                        @foreach ($navLinks as $link)
                            <li><a href="{{ $link['url'] }}" class="hover:text-white">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-white">Academics</h3>
                    <ul class="mt-3 space-y-2 text-xs">
                        @forelse ($school->academicLevels as $level)
                            <li><a href="{{ $homeUrl }}#academics" class="hover:text-white">{{ $level->name }}</a></li>
                        @empty
                            <li class="text-gray-500">Coming soon.</li>
                        @endforelse
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-white">Contact Us</h3>
                    <div class="relative mt-3">
                        <x-website-blocks :blocks="$footerBlocks->get('contact', collect())" height="{{ max($footerBlocks->get('contact', collect())->count() * 24, 24) }}px" />
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wide text-white">Newsletter</h3>
                    <p class="mt-3 text-xs text-gray-400">Subscribe for the latest updates.</p>
                    <div class="mt-3 flex">
                        <input type="email" disabled placeholder="Enter your email" class="rounded-r-none">
                        <span class="flex items-center justify-center rounded-r-[8px] bg-primary-600 px-3 text-white opacity-60" title="Coming soon">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 px-4 py-4 text-center text-xs text-gray-500 sm:px-6">
                &copy; {{ now()->year }} {{ $school->name }}. Powered by {{ config('app.name', 'EduNest') }}.
            </div>
        </footer>
    </body>
</html>
