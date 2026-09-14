@props(['school', 'website', 'title' => null, 'usedFonts' => []])

@php
    // The school's OWN address, not the /schools/{slug} path.
    //
    // A school reached at its own domain sits on "/", so a menu built from
    // the path-based URL pointed every anchor at a DIFFERENT address, the
    // browser reloaded the page and redirected back rather than scrolling.
    // publicUrl() resolves to whichever address this school is actually
    // reached at, so "#about" is a scroll on the page the visitor is on.
    $homeUrl = $school->publicUrl('public.school-website');

    // ONE PAGE. Every navigation item is an anchor on the front page, not a
    // separate document, so clicking one scrolls rather than reloads.
    //
    // ALL EIGHT, ALWAYS. Every one of these sections is rendered on the front
    // page whether or not the school has filled it in yet, an empty Gallery
    // says "nothing here yet", which is a true and useful thing for a parent
    // to learn, and it means no menu item can ever point at a section that is
    // not on the page.
    //
    // Facilities is deliberately NOT among them. The section is still on the
    // page and still has its own id, so a link to #facilities from anywhere
    // else keeps working, it is only off the menu, which was carrying more
    // items than a phone can show without wrapping.
    //
    // The href carries the full home URL, not a bare "#about", so the menu
    // still works from a separate News or Gallery page a visitor may have
    // arrived on, it takes them home and scrolls.
    $sectionLinks = collect([
        ['label' => 'Home', 'id' => 'home'],
        ['label' => 'About', 'id' => 'about'],
        ['label' => 'Academics', 'id' => 'academics'],
        ['label' => 'Admissions', 'id' => 'admissions'],
        ['label' => 'News', 'id' => 'news'],
        ['label' => 'Events', 'id' => 'events'],
        ['label' => 'Gallery', 'id' => 'gallery'],
        ['label' => 'Contact', 'id' => 'contact'],
    ]);

    $navLinks = $school->navLinks->isNotEmpty()
        ? $school->navLinks->map(fn ($link) => ['label' => $link->label, 'url' => $link->url])->all()
        : $sectionLinks->map(fn ($item) => ['label' => $item['label'], 'url' => $homeUrl.'#'.$item['id']])->all();
    [$brandPrimary, $brandSecondary] = collect(explode(' ', $school->name, 2))->pad(2, null)->all();
    $ctaDelay = 80 + count($navLinks) * 55 + 100;
    // Applying is a scroll, not a journey. The default sends a visitor
    // to the enquiry form further down the same page; a school that has
    // set its own admissions URL keeps it, because that is a deliberate
    // choice about where their applications go.
    $ctaUrl = $website->cta_url ?: $homeUrl.'#contact';
    // NO BACKDROP BLUR. Both of these carried backdrop-blur, which frosts
    // everything scrolling underneath the header, so the top of every
    // photograph and every card went soft as it passed behind. The bar is
    // opaque enough on its own; raising it from 95% to 98% costs nothing and a
    // solid bar is sharper than a blurred one at any opacity.
    //
    // It is also the single most expensive thing a sticky element can do: the
    // browser re-filters the region behind it on every frame of every scroll.
    $navbarScrolledClass = $website->navbar_bg_color ? 'bg-[var(--edn-navbar-bg)] shadow-md' : 'bg-white/98 shadow-md';
    $navbarUnscrolledClass = $website->navbar_bg_color ? 'bg-[var(--edn-navbar-bg)] shadow-none' : 'bg-white/95 shadow-none';
    $footerBlocks = $school->websiteBlocksFor('footer')->groupBy('section');

    // The school's chosen typeface joins whatever the blocks reference, so one
    // Google Fonts request covers the page rather than two.
    $allUsedFonts = array_merge_recursive(
        $usedFonts,
        \App\Support\GoogleFonts::usedInBlocks($footerBlocks->flatten(1)),
        \App\Support\WebsiteTypography::googleFontsFor($website),
    );
    $fontStack = \App\Support\WebsiteTypography::stackFor($website);
    $baseFontWeight = \App\Support\WebsiteTypography::weightFor($website);
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
        resolve to AkademicNest's own favicon rather than this school's (or none). --}}
        <x-favicon :school="$school" :platform-fallback="false" />
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

        {{-- The school's typography.
             ------------------------------------------------------------------

             On :root as custom properties, and applied to body from there, so
             one declaration reaches the whole page, navbar, hero, cards,
             forms, footer, rather than a class having to be added to each.

             THE BASE WEIGHT IS ON body ALONE, and that is deliberate. Every
             heading on this site carries its own font-bold or font-extrabold
             utility, and a class beats an inherited value, so the hierarchy
             survives untouched while unstyled body text follows whatever the
             school chose. Setting the weight on * instead would flatten the
             page to a single weight and lose the design.

             The family is a name out of the curated list, never a value a
             school typed, see App\Support\WebsiteTypography. --}}
        <style>
            :root {
                --edn-font-family: {!! $fontStack !!};
                --edn-font-weight: {{ $baseFontWeight }};
            }

            body {
                font-family: var(--edn-font-family);
                font-weight: var(--edn-font-weight);
            }
        </style>

        {{-- Single-page scrolling.

             scroll-padding-top on the root is what keeps a section from
             opening underneath the sticky header: the browser stops that far
             short of the target. Set here rather than as scroll-margin on each
             section, so a section added later cannot forget it.

             Anyone who has asked their system not to animate gets an instant
             jump instead, a full-page glide is exactly the motion that
             setting exists to switch off. --}}
        <style>
            html { scroll-behavior: smooth; scroll-padding-top: 96px; }
            {!! '@media (prefers-reduced-motion: reduce)' !!} { html { scroll-behavior: auto; } }
        </style>
    </head>
    {{-- No `font-sans` here. It is a CLASS, and a class beats the `body`
         element rule above it, so the school's chosen typeface would have
         been set, inherited by nothing, and silently overridden on the very
         element it was written for. The rule in the head is the font now. --}}
    <body class="bg-white text-gray-900 antialiased" x-data="{ mobileNavOpen: false }">
        {{-- A slim band above the header: the school's welcome on the left,
             the two things a visitor most often arrives for on the right. --}}
        <div class="bg-primary-700 px-4 py-1.5 text-[11.5px] font-medium text-white sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
                <span class="hidden truncate font-semibold sm:inline">{{ $website->topbar_announcement ?: "Welcome to {$school->name}" }}</span>
                <div class="flex w-full items-center justify-between gap-4 text-white/90 sm:w-auto sm:justify-end">
                    <span class="flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar shrink-0 text-[12px]" aria-hidden="true"></i>
                        {{ $website->topbar_badge_text ?: 'Admissions Open '.($school->current_session ?? now()->year) }}
                    </span>
                    {{-- Opens in its own tab. A visitor reading the website who wants
                         to sign in should still have the website when they
                         are done, signing in is an errand, not a departure. --}}
                    <a
                        href="{{ $website->topbar_link_url ?: $school->publicUrl('portal.index') }}"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-1.5 transition-colors duration-200 hover:text-white"
                    >
                        <i class="fa-regular fa-user shrink-0 text-[12px]" aria-hidden="true"></i>
                        {{ $website->topbar_link_text ?: 'School Portal' }}
                    </a>
                </div>
            </div>
        </div>

        <header
            {{-- Throttled to one read a frame, and passive.

                 A scroll event fires far more often than the screen refreshes,
                 and this used to set the property on every one of them, so a
                 single flick of a trackpad queued dozens of Alpine updates the
                 browser would never paint. Passive also tells the browser it
                 need not wait on this handler before scrolling at all. --}}
            x-data="{ scrolled: false, ticking: false }"
            x-init="
                scrolled = window.scrollY > 12;
                window.addEventListener('scroll', () => {
                    if (ticking) return;
                    ticking = true;
                    requestAnimationFrame(() => { scrolled = window.scrollY > 12; ticking = false });
                }, { passive: true });
            "
            :class="scrolled ? '{{ $navbarScrolledClass }}' : '{{ $navbarUnscrolledClass }}'"
            class="sticky top-0 z-30 border-b border-gray-100 transition-[background-color,box-shadow] duration-300 ease-out"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 transition-[padding] duration-300 ease-out sm:px-6"
                :class="scrolled ? 'py-2.5' : 'py-4'"
            >
                {{-- The crest is a disc, not a rounded square, and it is
                     smaller than the wordmark beside it, the school's name is
                     what identifies the site, the crest confirms it. Contained
                     rather than cropped, so a wide or tall crest keeps its
                     shape instead of being cut to fit. --}}
                <a href="{{ $homeUrl }}" class="edn-enter group flex shrink-0 items-center gap-2.5" style="animation-delay: 0ms">
                    @if ($school->logoUrl())
                        <img
                            src="{{ $school->logoUrl() }}"
                            alt="{{ $school->name }}"
                            class="h-11 w-11 shrink-0 rounded-full object-contain ring-1 ring-gray-200 transition-transform duration-300 ease-out group-hover:scale-105"
                            style="width: 44px; height: 44px; object-fit: contain;"
                        >
                    @else
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-700 text-lg font-extrabold text-white transition-transform duration-300 ease-out group-hover:scale-105" style="width: 44px; height: 44px;">
                            {{ Str::of($school->name)->substr(0, 1)->upper() }}
                        </span>
                    @endif
                    <span class="min-w-0 leading-none">
                        <span class="block truncate text-[22px] font-extrabold leading-none tracking-tight text-gray-900">{{ $brandPrimary }}</span>
                        @if ($brandSecondary)
                            <span class="mt-1 block truncate text-[9.5px] font-bold uppercase leading-none tracking-[0.2em] text-gray-600">{{ $brandSecondary }}</span>
                        @endif
                    </span>
                </a>

                {{-- Ranged right, ending against the Apply Now button, rather
                     than centred in whatever space the wordmark leaves, a
                     school with a long name would otherwise push the whole
                     menu off centre. --}}
                <nav class="hidden flex-1 items-center justify-end gap-0.5 xl:flex">
                    @foreach ($navLinks as $index => $link)
                        @php $isActive = request()->url() === $link['url']; @endphp
                        <a
                            href="{{ $link['url'] }}"
                            class="edn-enter edn-nav-link shrink-0 rounded-[6px] px-3 py-2 text-[14px] font-medium {{ $isActive ? 'is-active text-primary-700' : 'text-gray-700 hover:text-primary-800' }}"
                            style="animation-delay: {{ 80 + $index * 55 }}ms"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex shrink-0 items-center gap-2 xl:ml-4">
                    <a
                        href="{{ $ctaUrl }}"
                        class="edn-enter hidden items-center gap-1.5 rounded-[8px] bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-800 hover:shadow-md active:scale-[0.97] sm:flex"
                        style="animation-delay: {{ $ctaDelay }}ms"
                    >
                        {{ $website->cta_text ?: 'Apply Now' }}
                        <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                    </a>
                    <button
                        type="button"
                        @click="mobileNavOpen = !mobileNavOpen"
                        class="edn-enter flex h-11 w-11 items-center justify-center rounded-[8px] text-gray-500 transition-all duration-200 ease-out hover:scale-105 hover:bg-gray-100 active:scale-95 xl:hidden"
                        style="animation-delay: {{ $ctaDelay }}ms"
                    >
                        <i class="fa-solid fa-bars text-[18px]" x-show="!mobileNavOpen" aria-hidden="true"></i>
                        <i class="fa-solid fa-xmark text-[18px]" x-show="mobileNavOpen" style="display: none;" aria-hidden="true"></i>
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
                {{-- The items stagger in behind the drawer itself. The class
                     goes on while the drawer is open and comes off when it
                     closes, so they reset ready for next time. --}}
                x-bind:class="mobileNavOpen ? 'edn-drawer-open' : ''"
            >
                <div class="flex flex-col gap-1">
                    @foreach ($navLinks as $index => $link)
                        @php $isActive = request()->url() === $link['url']; @endphp
                        {{-- Closes the drawer on the way. Left open, it covers
                             the section the visitor just asked to see. --}}
                        <a
                            href="{{ $link['url'] }}"
                            @click="mobileNavOpen = false"
                            style="--edn-delay: {{ min($index * 55, 440) }}ms;"
                            class="edn-drawer-item rounded-[8px] px-3 py-2.5 text-[15px] font-semibold {{ $isActive ? 'bg-primary-50 text-primary-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    <a
                        href="{{ $ctaUrl }}"
                        @click="mobileNavOpen = false"
                        class="mt-2 flex items-center justify-center gap-1.5 rounded-[8px] bg-primary-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-all duration-200 hover:bg-primary-800"
                    >
                        {{ $website->cta_text ?: 'Apply Now' }}
                    </a>
                </div>
            </nav>
        </header>

        <main>
            {{ $slot }}
        </main>

        {{-- The band above the footer.

             It used to render positioned website-builder blocks into a fixed
             90px box, and they collided, the headline sat on top of the
             subline on top of the button, which is the overlapping text that
             has been visible on this page for a while. Blocks are placed by
             coordinate; a band that has to reflow from a phone to a desktop
             cannot be. So it is laid out properly here and reads its words from
             its own two columns.

             ALWAYS RENDERED, and with defaults, because a school that has
             written nothing still wants parents to know how to apply, and the
             band is where the page asks for the one thing it wants. --}}
        @php
            $ctaTitle = $website->cta_title ?: 'Give Your Child the Foundation for a Successful Future';
            $ctaSubtitle = $website->cta_subtitle ?: 'Admissions are open for the coming academic session.';
        @endphp

        <section class="bg-primary-700 px-4 py-7 sm:px-6">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
                <div class="edn-reveal flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-white/15 text-white">
                        <i class="fa-solid fa-graduation-cap text-[19px]" aria-hidden="true"></i>
                    </span>

                    <div class="min-w-0">
                        <p class="edn-reveal text-[16px] font-extrabold leading-snug text-white sm:text-[18px]" style="--edn-delay: 90ms;">{{ $ctaTitle }}</p>
                        <p class="edn-reveal mt-1 text-[12.5px] leading-relaxed text-white/85" style="--edn-delay: 180ms;">{{ $ctaSubtitle }}</p>
                    </div>
                </div>

                {{-- Heading, then paragraph, then the buttons, the small
                     stagger the spec asks of a CTA. --}}
                <div class="edn-reveal flex shrink-0 flex-wrap items-center gap-3" style="--edn-delay: 270ms;">
                    <a
                        href="{{ $ctaUrl }}"
                        class="inline-flex items-center gap-2 rounded-[8px] bg-white px-5 py-2.5 text-[13px] font-bold text-primary-700 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md"
                    >
                        {{ $website->cta_text ?: 'Apply for Admission' }}
                    </a>

                    {{-- Straight to the form on this page rather than to a
                         separate contact page, the same as every other
                         admission button on the site. --}}
                    <a
                        href="{{ $homeUrl }}#contact"
                        class="inline-flex items-center gap-2 rounded-[8px] border border-white/50 px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-300 ease-out hover:border-white hover:bg-white/10"
                    >
                        Contact Us
                        <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </section>

        {{-- The footer is not #contact. The Contact SECTION owns that
             anchor now, and two elements sharing an id means the browser
             scrolls to whichever it meets first, which was the footer, past
             everything the menu item was pointing at. --}}
        <footer id="site-footer" class="bg-[#0b1220] text-gray-300">
            {{-- The columns arrive 80ms apart, which is enough to read as a
                 sweep and not enough to keep anyone waiting at the bottom of
                 the page. --}}
            {{-- FOUR columns, not five. The school's own block is the wide
                 one, so the three link columns divide the rest evenly rather
                 than leaving a gap where the newsletter used to be. --}}
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                <div class="edn-reveal sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-2">
                        @if ($school->logoUrl())
                            <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="h-9 w-9 rounded-[8px] object-cover">
                        @else
                            <span class="flex h-9 w-9 items-center justify-center rounded-[8px] bg-primary-700 text-sm font-extrabold text-white">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                        @endif
                        <span class="text-sm font-extrabold text-white">{{ $school->name }}</span>
                    </div>
                    <div class="relative mt-3">
                        <x-website-blocks :blocks="$footerBlocks->get('description', collect())" height="70px" />
                    </div>
                    <div class="mt-4 flex gap-3">
                        {{-- Every network the school has filled in, from its
                             own settings, see App\Support\SchoolSocialLinks.
                             This used to be three hand-written blocks reading
                             the website record directly, which meant TikTok,
                             WhatsApp, YouTube and LinkedIn could be set in
                             Settings and appear nowhere. --}}
                        @foreach (\App\Support\SchoolSocialLinks::for($school) as $link)
                            <a
                                href="{{ $link['url'] }}"
                                target="_blank"
                                rel="noopener"
                                title="{{ $link['label'] }}"
                                aria-label="{{ $link['label'] }}"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-gray-300 transition-colors duration-150 hover:bg-primary-700 hover:text-white"
                            >
                                <i class="{{ $link['icon'] }} text-[14px]"></i>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="edn-reveal" style="--edn-delay: 80ms;">
                    <h3 class="edn-footer-heading text-xs font-bold uppercase tracking-wide text-white">Quick Links</h3>
                    <ul class="mt-3 space-y-2 text-xs">
                        @foreach ($navLinks as $link)
                            <li><a href="{{ $link['url'] }}" class="edn-footer-link">{{ $link["label"] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div class="edn-reveal" style="--edn-delay: 160ms;">
                    <h3 class="edn-footer-heading text-xs font-bold uppercase tracking-wide text-white">Academics</h3>
                    <ul class="mt-3 space-y-2 text-xs">
                        @forelse ($school->academicLevels as $level)
                            <li><a href="{{ $homeUrl }}#academics" class="edn-footer-link">{{ $level->name }}</a></li>
                        @empty
                            <li class="text-gray-500">Coming soon.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="edn-reveal" style="--edn-delay: 240ms;">
                    <h3 class="edn-footer-heading text-xs font-bold uppercase tracking-wide text-white">Contact Us</h3>
                    <div class="relative mt-3">
                        <x-website-blocks :blocks="$footerBlocks->get('contact', collect())" height="{{ max($footerBlocks->get('contact', collect())->count() * 24, 24) }}px" />
                    </div>
                </div>

                {{-- The newsletter column was here. It was a disabled input
                     and a "Coming soon" button, a signup that could not sign
                     anybody up, and it is gone rather than left to look
                     broken. The grid dropped from five columns to four with
                     it, so nothing is left holding an empty fifth. --}}
            </div>

            <div class="border-t border-white/10 px-4 py-4 text-center text-xs text-gray-500 sm:px-6">
                &copy; {{ now()->year }} {{ $school->name }}. Powered by {{ config('app.name', 'AkademicNest') }}.
            </div>
        </footer>
    </body>
</html>
