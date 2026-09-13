@php
    $slides = $heroSlides->isNotEmpty()
        ? $heroSlides->map->imageUrl()->filter()->values()->all()
        : array_values(array_filter([$website->heroImageUrl()]));

    // Each academic level gets a Font Awesome mark, a colour and a sentence
    // saying what that stage is for, chosen from the level's own name so a
    // school calling it "Lower Primary" or "Key Stage 2" still lands
    // somewhere sensible.
    //
    // THE COLOURS ARE THE SCHOOL'S OWN. These were four fixed hex values -
    // a green, an amber, a blue and a purple - which meant a school whose
    // brand is crimson still got a blue Junior card and a purple Senior one.
    // They are four SHADES OF THE ONE BRAND COLOUR now, read from the CSS
    // variables the layout writes from BrandColorScale.
    //
    // Four shades rather than one, because the point of the colours was to
    // tell the stages apart at a glance and a single value would flatten that.
    // 500 through 800 keeps the cards distinguishable while every one of them
    // belongs to the school's own palette - and the darker end is where the
    // contrast is: these tint a link a parent is meant to click.
    $academicStages = [
        'early' => [
            'icon' => 'fa-child-reaching',
            'color' => 'var(--color-primary-500)',
            'blurb' => 'Developing curiosity, confidence and foundational skills through engaging learning.',
        ],
        'primary' => [
            'icon' => 'fa-book-open-reader',
            'color' => 'var(--color-primary-600)',
            'blurb' => 'Building strong foundations in literacy, numeracy, creativity and character.',
        ],
        'junior' => [
            'icon' => 'fa-graduation-cap',
            'color' => 'var(--color-primary-700)',
            'blurb' => 'Developing independent thinking and strong academic foundations.',
        ],
        'senior' => [
            'icon' => 'fa-book',
            'color' => 'var(--color-primary-800)',
            'blurb' => 'Preparing students for WAEC, NECO, JAMB and higher education.',
        ],
    ];

    $academicStage = function (string $name) use ($academicStages) {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'creche'), str_contains($lower, 'nursery'),
            str_contains($lower, 'kindergarten'), str_contains($lower, 'kg'),
            str_contains($lower, 'early') => $academicStages['early'],

            str_contains($lower, 'primary'), str_contains($lower, 'basic') => $academicStages['primary'],

            str_contains($lower, 'jss'), str_contains($lower, 'junior') => $academicStages['junior'],

            default => $academicStages['senior'],
        };
    };
@endphp

@php
    $homeBlocks = $school->websiteBlocksFor('home')->groupBy('section');
@endphp

<x-public-site-layout :school="$school" :website="$website" :used-fonts="\App\Support\GoogleFonts::usedInBlocks($homeBlocks->flatten(1))">
    @php
        // A school that has never opened the website builder gets the default
        // design below. One that HAS arranged its own hero keeps the canvas it
        // dragged - replacing that with a fixed layout would throw away work
        // somebody did by hand.
        // $hasCustomBlocks is gone with the two branches that read it. It was
        // an existence query run on every page load whose only job was to
        // decide whether to replace this design with positioned blocks - and
        // nothing replaces it now.

        $heroTitle = $website->hero_title ?: $school->name;
        $heroTagline = \App\Support\SchoolMotto::for($school)->tagline;
        $heroBlurb = $website->hero_subtitle
            ?: 'Empowering students with knowledge, character and confidence to excel academically and become responsible leaders.';
        $heroSecondaryText = $website->hero_secondary_text ?: 'Explore Our School';
        $heroSecondaryUrl = $website->hero_secondary_url ?: '#academics';

        // The gold that runs through the whole site - the hero tagline, the
        // section rules, the footer. Warm enough to read as gold on the navy
        // and dark enough to stay legible if a school picks a pale secondary.
        $siteGold = '#e8b04b';
        $heroNavy = $website->brand_secondary_color ?: '#0d1b3e';
    @endphp

    {{-- SEGMENT 1 - the hero.

         FULL BLEED. The photograph is the hero: it fills the section top to
         bottom and edge to edge, and the heading, the tagline and the two
         buttons sit on top of it.

         This replaces a navy panel on the left with the photograph confined to
         the right, joined by a large disc. That design meant the picture only
         ever had part of the section - and once the hero grew to fill the
         screen, that part was a tall narrow column that cropped a landscape
         photograph down to a slice of itself. The panel and the disc are gone;
         there is no coloured container left for the image to sit inside.

         What keeps the words readable is a GRADIENT, not a panel and not a
         blur: dark where the text is, clear where it is not, so the
         photograph is genuinely visible rather than veiled. --}}
    <section
        id="home"
        class="relative overflow-hidden"
        style="background-color: {{ $heroNavy }};"
        x-data="{ slide: 0, total: {{ max(count($slides), 1) }} }"
        x-init="total > 1 && setInterval(() => slide = (slide + 1) % total, 6000)"
    >
        {{-- The photographs, filling the whole section.

             The parallax goes on THIS wrapper, not on a slide inside it. A
             slide owns its own transform - the slow scale - and a second
             transform written to the same element would simply replace the
             first, so the drift would cancel the scale. --}}
        <div class="edn-parallax absolute inset-0" data-parallax-rate="0.03" data-parallax-max="18">
            @forelse ($slides as $index => $slideUrl)
                {{-- A BASE LAYER, drawn once and never hidden.

                     Slides cross-fade, and a cross-fade between two
                     half-transparent images lets whatever is beneath show
                     through in the middle of it. With nothing beneath, that
                     was the section's own colour - a flash of navy between
                     every pair of slides. This layer sits under all of them at
                     full opacity so the stack is never transparent, and what
                     shows through mid-fade is a photograph rather than a
                     background. --}}
                @if ($loop->first)
                    <div
                        class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                        style="background-image: url('{{ $slideUrl }}');"
                        aria-hidden="true"
                    ></div>
                @endif

                {{-- COVER. The picture fills the section and accepts a crop
                     where its shape and the screen's disagree - which is the
                     right trade when the alternative is bare colour around it.

                     The scale never goes below 1, so a slide can never be
                     smaller than the area it is covering; at every point in
                     the animation the section is completely covered. --}}
                <div
                    class="edn-kenburns absolute inset-0 bg-cover bg-center bg-no-repeat"
                    style="background-image: url('{{ $slideUrl }}'); animation-delay: {{ $index * -3 }}s"
                    x-show="slide === {{ $index }}"
                    x-transition:enter="transition-opacity duration-1000"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-1000"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                ></div>
            @empty
                <div class="absolute inset-0" style="background: linear-gradient(140deg, {{ $heroNavy }} 0%, #1e3a6d 100%);"></div>
            @endforelse
        </div>

        {{-- The readability wash.

             A gradient, and a restrained one: opaque enough on the left that
             white type holds against any photograph a school uploads, and
             clear on the right so the picture is actually seen. There is no
             blur here and no frosted panel - the image stays sharp and the
             contrast comes from darkness alone.

             A second, shallower wash from the bottom carries the buttons,
             which sit lower than the text the left-hand gradient was drawn
             for. --}}
        <div
            class="absolute inset-0"
            style="background: linear-gradient(to right, color-mix(in srgb, {{ $heroNavy }} 68%, transparent) 0%, color-mix(in srgb, {{ $heroNavy }} 40%, transparent) 45%, color-mix(in srgb, {{ $heroNavy }} 6%, transparent) 100%);"
            aria-hidden="true"
        ></div>
        <div
            class="absolute inset-x-0 bottom-0 h-1/3"
            style="background: linear-gradient(to top, color-mix(in srgb, {{ $heroNavy }} 34%, transparent) 0%, transparent 100%);"
            aria-hidden="true"
        ></div>

        {{-- z-10, so the words sit above both washes and both photograph
             layers rather than relying on document order alone. --}}
        {{-- The content drifts too, and slightly FASTER than the photograph
             behind it - a small negative rate against the background's small
             positive one. Neither movement is large; what the eye reads is the
             difference between them, which is what makes the two layers feel
             like one scene with depth rather than a picture with text parked
             on it. Capped tightly so the words stay where they belong. --}}
        <div class="edn-hero edn-parallax relative z-10 mx-auto flex max-w-7xl items-center px-4 pb-24 pt-16 sm:px-6 lg:pb-28" data-parallax-rate="-0.045" data-parallax-max="26">
            {{-- Held to a measure rather than to the old 46% panel width. The
                 text is over the photograph now, not beside it, so what
                 governs the column is how long a line should be - not where a
                 navy rectangle happened to end. --}}
            {{-- THE DESIGNED HERO, for every school.

                 This used to be swapped out for page-builder blocks the moment
                 a school had saved any home-page block - and those blocks are
                 placed by absolute coordinate, which is why they collided into
                 unreadable overlapping text at any width but the one they were
                 arranged at. A school that had touched the builder once got
                 that instead of the design, for ever.

                 The wording is still the school's own: the title, the tagline
                 and the blurb all come from its website settings. What is
                 fixed is the LAYOUT, which is the part a coordinate system was
                 never going to get right across four breakpoints. --}}
            <div class="w-full max-w-xl lg:max-w-2xl">
                    <h1 class="edn-enter text-[40px] font-extrabold leading-[1.05] tracking-tight text-white sm:text-[52px]" style="line-height: 1.05;">
                        {{ $heroTitle }}
                    </h1>

                    @if ($heroTagline)
                        <p class="edn-enter mt-3 text-[16px] font-bold" style="color: {{ $siteGold }}; animation-delay: 100ms;">{{ $heroTagline }}</p>
                    @endif

                    <p class="edn-enter mt-4 max-w-md text-[14.5px] leading-relaxed text-white/90" style="animation-delay: 180ms;">
                        {{ $heroBlurb }}
                    </p>

                    <div class="edn-enter mt-7 flex flex-wrap items-center gap-3" style="animation-delay: 260ms;">
                        <a
                            href="{{ $website->cta_url ?: '#contact' }}"
                            class="inline-flex items-center gap-2 rounded-[8px] bg-primary-700 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-800 hover:shadow-md"
                        >
                            {{ $website->cta_text ?: 'Apply for Admission' }}
                        </a>
                        {{-- 1.3px in the school's own colour, as the brief
                             asks. The LABEL stays white: this sits on the navy
                             hero panel, and a mid-tone brand colour as type on
                             navy is the one place the theme colour cannot go -
                             the contrast rule outranks the branding rule. The
                             border carries the brand instead, where a lower
                             contrast is not a readability problem. --}}
                        <a
                            href="{{ $heroSecondaryUrl }}"
                            style="border: 1.3px solid var(--color-primary-400);"
                            class="inline-flex items-center gap-2 rounded-[8px] px-6 py-3 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-white/10"
                        >
                            {{ $heroSecondaryText }}
                            <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                        </a>
                    </div>
            </div>
        </div>

        @if (count($slides) > 1)
            <div class="absolute bottom-8 left-0 right-0 z-10">
                <div class="mx-auto flex max-w-7xl gap-2 px-4 sm:px-6 lg:pl-[46%]">
                    @foreach ($slides as $index => $slideUrl)
                        <button
                            type="button"
                            @click="slide = {{ $index }}"
                            aria-label="Show slide {{ $index + 1 }}"
                            class="h-2 rounded-full transition-all duration-300"
                            :class="slide === {{ $index }} ? 'w-7 bg-primary-500' : 'w-2 bg-white/50'"
                        ></button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- SEGMENT 2 - the figures that answer "how big, how established?".

         A white card lifted over the hero's bottom edge, four columns divided
         by hairlines. The numbers are the school's own - see
         SchoolWebsite::stats - and the icon beside each is chosen from what
         the school called it, so "Qualified Teachers" gets a graduate's cap
         whatever position it sits in. --}}
    @php
        // Font Awesome solid, which is the heaviest weight the free set
        // carries - drawn as glyphs rather than hand-written SVG paths, so
        // they match the icons used everywhere else in the application and a
        // school adding a fifth stat gets a real icon rather than a guess.
        $iconForStat = function (string $label): string {
            $lower = strtolower($label);

            return match (true) {
                str_contains($lower, 'student'), str_contains($lower, 'pupil'), str_contains($lower, 'children') => 'fa-user-group',
                str_contains($lower, 'teacher'), str_contains($lower, 'staff'), str_contains($lower, 'tutor') => 'fa-graduation-cap',
                str_contains($lower, 'year'), str_contains($lower, 'excellence'), str_contains($lower, 'award') => 'fa-trophy',
                str_contains($lower, 'class') => 'fa-chalkboard-user',
                str_contains($lower, 'level'), str_contains($lower, 'stage') => 'fa-layer-group',
                str_contains($lower, 'subject'), str_contains($lower, 'course') => 'fa-book-open',
                default => 'fa-book-open',
            };
        };

        $siteStats = collect($website->stats ?: [])
            ->filter(fn ($stat) => filled($stat['label'] ?? null))
            ->take(4)
            ->values();

        // A stat's value is free text - a school writes "1,200", "25+" or
        // "Grade A". Only the ones that START with a number can be counted up
        // to, so this splits off the number and whatever trails it and returns
        // null for anything else, which then renders as the plain words it is.
        $countable = function (string $value): ?array {
            if (! preg_match('/^\s*([\d,]+)\s*(.*)$/u', $value, $matches)) {
                return null;
            }

            return [(int) str_replace(',', '', $matches[1]), trim($matches[2])];
        };
    @endphp

    {{-- The designed bar, for every school, for the same reason as the hero
         above: a page-builder version of this was positioned by coordinate and
         collapsed at any width other than the one it was arranged at. The
         figures are still the school's own, from its website settings. --}}
    @if ($siteStats->isNotEmpty())
        <section class="relative z-10 -mt-14 px-4 sm:px-6">
            <div class="edn-enter mx-auto max-w-6xl overflow-hidden rounded-[12px] bg-white shadow-xl shadow-black/10" style="animation-delay: 340ms;">
                {{-- The column count is real CSS, not an interpolated class.
                     `sm:grid-cols-{{ $n }}` reads fine and renders nothing:
                     Tailwind scans this file at build time and never sees the
                     finished class name, so the grid would silently collapse
                     to one column. A scoped rule also survives a resize,
                     which a JavaScript width check would not. --}}
                @php $statsGridId = 'edn-stats-'.$website->uuid; @endphp
                <style>
                    #{{ $statsGridId }} { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
                    {{-- Written out rather than escaped with @@, which eats
                         the space and leaves @media(min-width…). --}}
                    {!! '@media (min-width: 640px)' !!} {
                        #{{ $statsGridId }} { grid-template-columns: repeat({{ $siteStats->count() }}, minmax(0, 1fr)); }
                    }
                </style>
                <div id="{{ $statsGridId }}">
                    @foreach ($siteStats as $index => $stat)
                        {{-- Stacked and centred on a phone, side by side from
                             the small breakpoint up. Inline at 320px would
                             leave "Academic Programmes" two words to a line
                             beside an icon, which is where this sort of bar
                             usually falls apart. --}}
                        <div
                            class="edn-reveal flex flex-col items-center justify-center gap-2 px-3 py-5 text-center sm:flex-row sm:gap-3 sm:px-6 sm:py-6 sm:text-left {{ $index > 0 ? 'sm:border-l sm:border-gray-200' : '' }} {{ $index % 2 === 1 ? 'border-l border-gray-200' : '' }} {{ $index > 1 ? 'border-t border-gray-200 sm:border-t-0' : '' }}"
                            style="--edn-delay: {{ $index * 90 }}ms;"
                        >
                            <i class="fa-solid {{ $iconForStat($stat['label']) }} shrink-0 text-[22px] text-primary-700 sm:text-[26px]" aria-hidden="true"></i>

                            <div class="min-w-0">
                                @php $counted = $countable((string) ($stat['value'] ?? '')); @endphp

                                <p class="text-[20px] font-extrabold leading-none text-gray-900 sm:text-[22px]">
                                    @if ($counted)
                                        {{-- Counts once, when the bar is first
                                             seen. The final value is in the
                                             attribute, so a visitor with
                                             reduced motion or no JavaScript
                                             reads the real figure rather than
                                             a zero that never moves. --}}
                                        <span
                                            data-count-to="{{ $counted[0] }}"
                                            data-count-suffix="{{ $counted[1] }}"
                                            data-count-duration="{{ 1400 + $index * 120 }}"
                                        >{{ $stat['value'] }}</span>
                                    @else
                                        {{ $stat['value'] ?? '—' }}
                                    @endif
                                </p>
                                <p class="mt-1.5 text-[12px] font-medium leading-tight text-gray-600 sm:text-[12.5px]">{{ $stat['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- SEGMENT 4 - academics.

         Always on the page, because Academics is in the menu and a menu item
         must have somewhere to land. A school still setting up says so. --}}
    {{-- The background a School Admin set, or the flat colour this section has
         always had when they have not set one.

         The image is the SECTION's background, not each card's - the four
         level cards keep their own white, their own borders, their own hover
         lift. Nothing about them changes.

         The overlay is what keeps the heading readable. The text over it is
         near-black, so the wash is white rather than dark: a dark scrim would
         mean recolouring the type, and the rule is that near-black text sits on
         a light ground. 82% is enough to read through and light enough to see
         the photograph. --}}
    @php $academicsBackground = $website->academicsCardImageUrl(); @endphp

    <section
        id="academics"
        class="relative scroll-mt-24 overflow-hidden px-4 py-14 sm:px-6 lg:py-16"
        @unless ($academicsBackground) style="background-color: #f6f9fd;" @endunless
    >
        @if ($academicsBackground)
            {{-- Its OWN LAYER rather than the section's background-image,
                 because a background cannot be transformed - only the element
                 carrying it can, and transforming the section would move the
                 text with it. As a layer it can drift on the compositor while
                 the content stays put.

                 Inset 50px beyond the section top and bottom so the 40px of
                 travel never uncovers an edge; the section clips the rest. --}}
            <div
                class="edn-parallax absolute inset-x-0"
                data-parallax-rate="0.06"
                data-parallax-max="40"
                style="top: -50px; bottom: -50px; background-image: url('{{ $academicsBackground }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
                aria-hidden="true"
            ></div>

            {{-- An even tint. It was a white gradient, held stronger here than
                 on the News row because this section has more bare text over
                 the picture - but white over a photograph is what made it look
                 washed out in the first place, so the whole run of text goes
                 light instead and the tint can be one flat value. --}}
            <div class="edn-photo-scrim absolute inset-0" aria-hidden="true"></div>
        @endif

        {{-- The drift is conditional on there BEING a photograph. Depth is the
             relationship between two layers moving at different rates; with a
             flat background there is no second layer, and content sliding on
             its own over plain colour reads as a rendering fault rather than
             as depth. --}}
        <div
            class="relative mx-auto max-w-7xl {{ $academicsBackground ? 'edn-parallax' : '' }}"
            @if ($academicsBackground) data-parallax-rate="-0.04" data-parallax-max="24" @endif
        >
            <div class="text-center">
                <p class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] {{ $academicsBackground ? 'edn-on-photo-brand' : 'text-primary-700' }}">Academics</p>
                <h2 class="edn-reveal mt-2 text-[26px] font-extrabold leading-tight tracking-tight sm:text-[28px] {{ $academicsBackground ? 'edn-on-photo' : 'text-gray-900' }}" style="--edn-delay: 110ms;">Academic Excellence</h2>
                {{-- The accent rule is drawn 250ms after whatever delay it is
                     given, so the eyebrow, the heading, the rule and the
                     paragraph arrive as one layered reveal rather than four
                     separate events. --}}
                <span class="edn-accent mx-auto mt-3 block h-[3px] w-12 rounded-full {{ $academicsBackground ? 'bg-white' : 'bg-primary-700' }}" style="--edn-delay: 110ms;" aria-hidden="true"></span>

                <p class="edn-reveal mx-auto mt-3 max-w-2xl text-[13.5px] leading-relaxed {{ $academicsBackground ? 'edn-on-photo-muted' : 'text-gray-700' }}" style="--edn-delay: 200ms;">
                    We offer a broad and balanced curriculum across all levels that prepares students for a successful future.
                </p>
            </div>

            {{-- Each level in a flat white card: a coloured mark, the level's
                 name, what it is for, and the way in.

                 The colour runs through the icon and the link but never the
                 body text, which stays near-black. A card whose wording is
                 tinted to match its icon is a card a parent squints at. --}}
            <div class="mt-9 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($academicLevels as $level)
                    @php $stage = $academicStage($level->name); @endphp

                    {{-- 100ms apart, so the row assembles left to right rather
                         than appearing all at once.

                         The reveal is on a WRAPPER, not on the card. The card
                         already carries `transition-all duration-300` for its
                         hover lift, and .edn-reveal sets `transition` too -
                         one shorthand would overwrite the other and whichever
                         won would break the effect that lost. A wrapper keeps
                         the arrival and the hover as separate concerns, which
                         is what they are. --}}
                    <div class="edn-reveal flex" style="--edn-delay: {{ $loop->index * 100 }}ms;">
                    <div class="edn-brand-outline group flex w-full flex-col rounded-[12px] bg-white p-5 text-center shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg">
                        <i class="fa-solid {{ $stage['icon'] }} mx-auto block text-[30px]" style="color: {{ $stage['color'] }};" aria-hidden="true"></i>

                        <h3 class="mt-3.5 text-[15px] font-bold text-gray-900">{{ $level->name }}</h3>

                        <p class="mt-2 flex-1 text-[12.5px] leading-relaxed text-gray-700">{{ $stage['blurb'] }}</p>

                        <a
                            href="#admissions"
                            class="mt-4 inline-flex items-center justify-center gap-1.5 text-[12px] font-bold transition-transform duration-200 group-hover:translate-x-0.5"
                            style="color: {{ $stage['color'] }};"
                        >
                            Learn More
                            <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                        </a>
                    </div>
                    </div>
                @empty
                    <p class="col-span-1 rounded-[10px] border border-dashed border-gray-300 bg-white p-8 text-center text-[13px] font-medium text-gray-700 sm:col-span-2 lg:col-span-4">
                        Academic levels have not been set up yet.
                    </p>
                @endforelse
            </div>
        </div>
    </section>


    {{-- What people say --}}
    @if ($testimonials->isNotEmpty())
        <section class="border-t border-gray-100 bg-gray-50 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <div class="text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-700">Testimonials</p>
                    <h2 class="mt-2 text-2xl font-extrabold text-gray-900">What People Say</h2>
                </div>
                <div class="mt-8 flex snap-x gap-5 overflow-x-auto pb-4">
                    @foreach ($testimonials as $testimonial)
                        <div class="w-72 shrink-0 snap-start rounded-[10px] bg-white p-5 shadow-sm">
                            <p class="text-2xl font-serif leading-none text-primary-200">&ldquo;</p>
                            <p class="mt-1 text-sm italic leading-relaxed text-gray-600">{{ Str::limit($testimonial->quote, 160) }}</p>
                            <div class="mt-4 flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-extrabold text-primary-700">{{ Str::of($testimonial->name)->substr(0, 1) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-gray-900">{{ $testimonial->name }}</p>
                                    @if ($testimonial->role)
                                        <p class="truncate text-xs text-gray-500">{{ $testimonial->role }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Campus video + Slogan + Job Portal --}}
    @if ($website->campusVideoEmbedUrl() || $homeBlocks->has('slogan') || $openJobs->isNotEmpty())
        <section class="px-4 py-16 sm:px-6">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-3">
                @if ($website->campusVideoEmbedUrl())
                    <div id="campus-life" class="scroll-mt-20 overflow-hidden rounded-[10px] shadow-lg lg:col-span-1">
                        <div class="aspect-video">
                            <iframe src="{{ $website->campusVideoEmbedUrl() }}" class="h-full w-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    </div>
                @endif

                @if ($homeBlocks->has('slogan'))
                    <div class="flex flex-col justify-center rounded-[10px] bg-gray-900 p-6 text-center">
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-300">Our School Slogan</p>
                        <div class="relative mt-2">
                            <x-website-blocks :blocks="$homeBlocks->get('slogan')" height="70px" />
                        </div>
                        @if ($website->slogan_tagline)
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-primary-300">{{ $website->slogan_tagline }}</p>
                        @endif
                    </div>
                @endif

                @if ($openJobs->isNotEmpty())
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Job Portal</h2>
                            <a href="{{ $school->publicUrl('public.jobs.index') }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">View All Jobs</a>
                        </div>
                        <div class="mt-4 overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm">
                            @foreach ($openJobs as $job)
                                <a href="{{ $job->setRelation('school', $school)->publicUrl() }}" class="flex items-center justify-between gap-3 border-b border-gray-100 p-4 transition-colors duration-150 last:border-b-0 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $job->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $job->employment_type->label() }}@if ($job->location) &middot; {{ $job->location }} @endif</p>
                                    </div>
                                    <span class="shrink-0 rounded-[8px] border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700">View Job</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- SEGMENT 3 - about the school.

         The text on the left, a photograph on the right, and beneath the
         prose the three statements a parent is actually weighing: what the
         school is for, where it is going, and what it will not compromise on.

         Each of the three appears only when the school has written it. Three
         empty columns under a heading would say less than one filled one. --}}
    @php
        $aboutBlockBody = $school->websiteBlocksFor('about')->firstWhere('section', 'body');
        $aboutText = $website->about_text ?: ($aboutBlockBody['content'] ?? null);

        // Always rendered, and always with something in it. The About
        // block is part of the design, not a section that appears once a
        // school gets round to writing prose - a front page with a hole where
        // "About" should be is worse than one with a plain sentence.
        $aboutText = $aboutText ?: $school->name.' is committed to providing a nurturing and challenging environment where every pupil is known, supported and stretched.';

        $aboutPillars = collect([
            ['label' => 'Our Mission', 'icon' => 'fa-bullseye', 'body' => $website->mission],
            ['label' => 'Our Vision', 'icon' => 'fa-eye', 'body' => $website->vision],
            ['label' => 'Our Values', 'icon' => 'fa-hand-holding-heart', 'body' => $website->values],
        ])->filter(fn ($pillar) => filled($pillar['body']))->values();
    @endphp

    @php $aboutBackground = $website->aboutCardImageUrl(); @endphp

    <section
        id="about"
        class="relative scroll-mt-20 overflow-hidden px-4 py-14 sm:px-6 lg:py-16"
        @unless ($aboutBackground) style="background-color: #ffffff;" @endunless
    >
        @if ($aboutBackground)
            {{-- Its own drifting layer, tinted black - the same treatment as
                 the Academics and News bands. See the note on Academics for
                 why this is a layer rather than the section's own
                 background-image. --}}
            <div
                class="edn-parallax absolute inset-x-0"
                data-parallax-rate="0.06"
                data-parallax-max="40"
                style="top: -50px; bottom: -50px; background-image: url('{{ $aboutBackground }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
                aria-hidden="true"
            ></div>

            <div class="edn-photo-scrim absolute inset-0" aria-hidden="true"></div>
        @endif

            {{-- ONE CENTRED COLUMN.

                 This was two: prose on the left, a large framed photograph on
                 the right. The photograph is gone, so there is no second half
                 to balance against and no reason to hold the text to 46% of
                 the page - it reads down the middle now, and everything in it
                 is a size larger than it was.

                 max-w-4xl rather than the section's 7xl because centred prose
                 needs a measure. Running a paragraph the full width of a
                 desktop is how a centred column stops being readable. --}}
            <div
                class="relative mx-auto max-w-4xl text-center {{ $aboutBackground ? 'edn-parallax' : '' }}"
                @if ($aboutBackground) data-parallax-rate="-0.04" data-parallax-max="24" @endif
            >
                <div class="edn-reveal">
                    {{-- Every line here sits directly on the section, so when
                         there is a tinted photograph behind it the whole run
                         goes light - the same rule as the Academics band. --}}
                    <p class="edn-reveal text-[12.5px] font-bold uppercase tracking-[0.18em] {{ $aboutBackground ? 'edn-on-photo-brand' : 'text-primary-700' }}">About Us</p>

                    <h2 class="edn-reveal mt-2.5 text-[32px] font-extrabold leading-tight tracking-tight sm:text-[38px] {{ $aboutBackground ? 'edn-on-photo' : 'text-gray-900' }}" style="--edn-delay: 110ms;">
                        About {{ $school->name }}
                    </h2>

                    {{-- The rule the other sections have. It was left out here
                         while the section was two columns; centred, the block
                         wants the same anchor they get. --}}
                    <span class="edn-accent mx-auto mt-4 block h-[3px] w-14 rounded-full {{ $aboutBackground ? 'bg-white' : 'bg-primary-700' }}" style="--edn-delay: 110ms;" aria-hidden="true"></span>

                    @if ($website->about_headline)
                        <p class="edn-reveal mt-6 text-[19px] font-bold leading-snug {{ $aboutBackground ? 'edn-on-photo' : 'text-gray-900' }}" style="--edn-delay: 190ms;">{{ $website->about_headline }}</p>
                    @endif

                    @if (filled($aboutText))
                        <p class="edn-reveal mx-auto mt-4 max-w-3xl whitespace-pre-line text-[16px] leading-relaxed {{ $aboutBackground ? 'edn-on-photo-muted' : 'text-gray-700' }}" style="--edn-delay: 260ms;">{{ $aboutText }}</p>
                    @endif

                    @if ($aboutPillars->isNotEmpty())
                        {{-- Divided by hairlines on a wide screen and stacked
                             on a narrow one, where three columns of small
                             print would be unreadable. --}}
                        @php
                            // Whole class names, not an interpolated number.
                            // Tailwind scans this file for finished strings;
                            // "sm:grid-cols-{$n}" is never one of them.
                            $pillarColumns = match ($aboutPillars->count()) {
                                1 => 'sm:grid-cols-1',
                                2 => 'sm:grid-cols-2',
                                default => 'sm:grid-cols-3',
                            };
                        @endphp
                        {{-- The hairlines lighten too. A gray-200 rule is
                             invisible on a dark photograph, so the three
                             columns would read as one block of text. --}}
                        <div class="mt-10 grid grid-cols-1 gap-8 border-t pt-8 {{ $pillarColumns }} sm:gap-6 {{ $aboutBackground ? 'border-white/30' : 'border-gray-200' }}">
                            @foreach ($aboutPillars as $index => $pillar)
                                {{-- Centred like the rest of the block, so the
                                     hairlines divide three equal columns rather
                                     than three left-aligned ones. --}}
                                <div
                                    class="edn-reveal text-center {{ $index > 0 ? ($aboutBackground ? 'sm:border-l sm:border-white/30 sm:pl-6' : 'sm:border-l sm:border-gray-200 sm:pl-6') : '' }}"
                                    style="--edn-delay: {{ 330 + $index * 90 }}ms;"
                                >
                                    {{-- "Our Mission", "Our Vision" and "Our
                                         Values" carry the school's colour, and
                                         so do their icons - it was near-black
                                         type beside a coloured mark, which
                                         made the icon the only branded thing
                                         in the row.

                                         Over a tinted photograph both go white
                                         instead: the contrast rule still
                                         outranks the branding rule. --}}
                                    {{-- The icon INHERITS. It carried its own
                                         colour class, which had to be kept in
                                         step with the heading's by hand - and
                                         over a photograph the two were not
                                         quite the same: the heading had the
                                         shadow that makes white type hold on a
                                         picture and the icon, setting its own
                                         colour, read flatter beside it.

                                         Given nothing of its own it takes the
                                         heading's colour AND its shadow, so
                                         the mark and the words are one thing
                                         in both states and cannot drift
                                         apart. --}}
                                    <p class="flex items-center justify-center gap-2 text-[15px] font-bold {{ $aboutBackground ? 'edn-on-photo-brand' : 'text-primary-700' }}">
                                        <i class="fa-solid {{ $pillar['icon'] }} text-[15px]" aria-hidden="true"></i>
                                        {{ $pillar['label'] }}
                                    </p>
                                    <p class="mt-2 text-[14px] leading-relaxed {{ $aboutBackground ? 'edn-on-photo-muted' : 'text-gray-700' }}">{{ $pillar['body'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Wrapped, not classed: the button carries
                         `transition-all` for its hover lift and .edn-reveal
                         sets `transition` too. --}}
                    <div class="edn-reveal" style="--edn-delay: 600ms;">
                        <a
                            href="{{ route('public.school-about.index', $school) }}"
                            class="mt-7 inline-flex items-center gap-2 rounded-[8px] bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-800 hover:shadow-md"
                        >
                            Learn More
                            <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

    {{-- SEGMENT 4 - admissions.

         The invitation on the left with what the school offers, and the
         process on the right as numbered steps. Both read from the school's
         own admissions settings; the steps fall back to the four every school
         runs, because a school that has not written its process still has one
         and a visitor still needs to see it. --}}
    @php
        $admissionSession = $school->current_session ?: (now()->year.'/'.(now()->year + 1));

        $admissionPoints = collect($website->admissions_requirements ?: [])
            ->filter(fn ($point) => filled($point))
            ->values();

        if ($admissionPoints->isEmpty() && $academicLevels->isNotEmpty()) {
            $admissionPoints = collect([
                $academicLevels->first()->name.' to '.$academicLevels->last()->name,
                'Conducive learning environment',
                'Experienced and qualified teachers',
                'Modern facilities and resources',
            ]);
        }

        $admissionSteps = collect($website->admissions_steps ?: [])
            ->filter(fn ($step) => filled($step['title'] ?? $step))
            ->values();

        if ($admissionSteps->isEmpty()) {
            $admissionSteps = collect([
                ['title' => 'Submit Application', 'body' => 'Fill and submit the online application form.', 'icon' => 'fa-file-lines'],
                ['title' => 'Application Review', 'body' => 'Our team reviews the application and supporting documents.', 'icon' => 'fa-users'],
                ['title' => 'Assessment / Interview', 'body' => 'Assessment or interview may be conducted.', 'icon' => 'fa-clipboard-check'],
                ['title' => 'Admission Offer', 'body' => 'Successful applicants will receive an admission offer.', 'icon' => 'fa-circle-check'],
            ]);
        }
    @endphp

    <section id="admissions" class="scroll-mt-24 bg-white px-4 py-14 sm:px-6 lg:py-16">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:gap-12">
            <div>
                <p class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] text-primary-700">Admissions</p>

                <h2 class="edn-reveal mt-2 text-[26px] font-extrabold leading-tight tracking-tight text-gray-900 sm:text-[28px]" style="--edn-delay: 110ms;">
                    Admissions Open
                    <span class="block underline decoration-primary-700 decoration-[3px] underline-offset-[6px]">{{ $admissionSession }}</span>
                </h2>

                <p class="mt-5 text-[14px] leading-relaxed text-gray-700">
                    {{ $website->admissions_intro ?: 'We welcome applications from parents who seek quality education for their children.' }}
                </p>

                @if ($admissionPoints->isNotEmpty())
                    <ul class="mt-5 space-y-2.5">
                        @foreach ($admissionPoints as $point)
                            {{-- The tick is the same blue as the number badges
                                 and the arrows across the row, so the two
                                 halves of this section read as one. --}}
                            <li class="flex items-start gap-2.5 text-[13.5px] font-medium text-gray-900">
                                <i class="fa-solid fa-circle-check mt-[3px] shrink-0 text-[15px] text-primary-700" aria-hidden="true"></i>
                                <span>{{ is_array($point) ? ($point['title'] ?? '') : $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a
                    href="{{ $website->cta_url ?: '#contact' }}"
                    class="mt-7 inline-flex items-center gap-2 rounded-[8px] bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-800 hover:shadow-md"
                >
                    {{ $website->cta_text ?: 'Apply Now' }}
                    <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                </a>
            </div>

            <div>
                <h3 class="text-center text-[17px] font-bold text-gray-900">Our Admission Process</h3>

                {{-- One column on a phone, two on a tablet, four across on a
                     desktop - the arrows between them only make sense once
                     the steps are actually in a row, so they appear at the
                     same breakpoint the row does.

                     The number badge, the icon and the arrow are all the one
                     blue. Only the step's name and its sentence are dark; a
                     card where everything is tinted has nothing to look at
                     first. --}}
                {{-- The gap is 28px at every size, which is what leaves room
                     for an arrow to sit IN it rather than clipped to a card's
                     edge. Both arrows are offset by half the gap plus half
                     the glyph, so each lands on the centre line between two
                     cards. --}}
                <div class="mt-5 grid grid-cols-1 gap-7 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($admissionSteps as $index => $step)
                        {{-- 120ms apart. Wrapped rather than classed for the
                             same reason as the academic cards: the card owns
                             `transition-all` for its hover, and two
                             `transition` shorthands on one element cannot both
                             win. The wrapper does not clip, so the arrows that
                             sit in the gap between cards still show. --}}
                        <div class="edn-reveal flex" style="--edn-delay: {{ $index * 120 }}ms;">
                        <div class="edn-brand-outline relative flex w-full flex-col items-center rounded-[10px] bg-white px-4 py-6 text-center shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-md">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-700 text-[12.5px] font-bold text-white" style="width: 36px; height: 36px;">
                                {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                            </span>

                            <i class="fa-solid {{ $step['icon'] ?? 'fa-file-lines' }} mt-4 block text-[30px] text-primary-700" aria-hidden="true"></i>

                            <p class="mt-3.5 text-[13.5px] font-bold leading-snug text-gray-900">{{ $step['title'] ?? '' }}</p>
                            <p class="mt-1.5 text-[12px] leading-relaxed text-gray-700">{{ $step['body'] ?? ($step['description'] ?? '') }}</p>

                            @unless ($loop->last)
                                {{-- The arrow follows the reading order, and
                                     the reading order changes with the number
                                     of columns.

                                     One column: the next card is BELOW, so the
                                     arrow points down.

                                     Two columns: cards read 1-2 / 3-4. After
                                     the first of a pair the next card is to
                                     the right; after the second it is on the
                                     next line, and an arrow pointing right
                                     there would aim off the edge of the row -
                                     so that one waits for the four-across
                                     layout, where right is true again. --}}
                                {{-- The show/hide class goes on a WRAPPER, not
                                     on the icon itself.

                                     Font Awesome sets `display` on .fa-solid,
                                     which is a single class - exactly as
                                     specific as Tailwind's `hidden`. At equal
                                     specificity the later stylesheet wins, and
                                     Font Awesome is imported after the
                                     utilities, so `sm:hidden` on an icon
                                     silently loses and the icon shows at every
                                     width. A plain span is not a Font Awesome
                                     element, so nothing argues with it. --}}
                                {{-- Both arrows are boxed at a known 18px and
                                     offset by half the gap plus half the box,
                                     so each one's CENTRE lands exactly on the
                                     midpoint between two cards. Left to size
                                     itself, a glyph centres on wherever its
                                     line box happens to fall, which is close
                                     but never quite right. --}}
                                <span
                                    class="absolute left-1/2 flex -translate-x-1/2 items-center justify-center sm:hidden"
                                    style="bottom: -23px; width: 18px; height: 18px;"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid fa-arrow-down text-[15px] leading-none text-primary-700"></i>
                                </span>

                                <span
                                    class="absolute top-1/2 hidden -translate-y-1/2 items-center justify-center {{ $index % 2 === 0 ? 'sm:flex' : 'lg:flex' }}"
                                    style="right: -23px; width: 18px; height: 18px;"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid fa-arrow-right text-[15px] leading-none text-primary-700"></i>
                                </span>
                            @endunless
                        </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- SEGMENT 5 - news and events, side by side.

         ALWAYS RENDERED, both of them, because both are in the menu and a
         menu item must have somewhere to land. A school with no news yet says
         so, which is a true and useful thing for a parent to read. --}}
    {{-- ONE background for both panels.

         Latest News and Upcoming Events are one card as far as a school is
         concerned, so there is one setting and one image, and it stays put
         while the content rotates between stories and events. Two images here
         would make them two cards.

         Nothing about either panel changes: same 312px tracks, same groups of
         three, same 3s rise, same 30s dwell, same scrolling. The image sits
         behind them.

         The white wash keeps the near-black card text and the primary-700
         headings readable over whatever photograph a school chooses - the
         cards themselves are white, so a dark scrim would fight them. --}}
    @php $newsEventsBackground = $website->newsEventsCardImageUrl(); @endphp

    <section
        class="relative overflow-hidden px-4 py-14 sm:px-6 lg:py-16"
        @unless ($newsEventsBackground) style="background-color: #f9fafb;" @endunless
    >
        @if ($newsEventsBackground)
            {{-- Its own drifting layer - see the note on the Academics section
                 for why this is not the section's own background-image. --}}
            <div
                class="edn-parallax absolute inset-x-0"
                data-parallax-rate="0.06"
                data-parallax-max="40"
                style="top: -50px; bottom: -50px; background-image: url('{{ $newsEventsBackground }}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
                aria-hidden="true"
            ></div>

            {{-- Only the two column headings sit on the photograph with nothing
                 behind them; the cards below are translucent by design, so the
                 wash falls away quickly and lets the picture show through
                 them. --}}
            <div class="edn-photo-scrim absolute inset-0" aria-hidden="true"></div>
        @endif

        {{-- The shared News & Events card drifts as one block, which keeps the
             two panels moving together. Nothing inside it is touched: the
             three-at-a-time rotation, the 30-second dwell, the slide-up and
             the translucency all run exactly as they did, on their own
             elements. This is a layer above them, not a change to them. --}}
        <div
            class="relative mx-auto grid max-w-7xl grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:gap-10 {{ $newsEventsBackground ? 'edn-parallax' : '' }}"
            @if ($newsEventsBackground) data-parallax-rate="-0.04" data-parallax-max="24" @endif
        >
            @php
                // Three or fewer fit on the page at once, so they simply sit
                // there - a carousel of three that a visitor can already see
                // in full is motion for its own sake, and it hides two of them
                // behind a thirty-second wait for no reason.
                //
                // More than three is where rotating earns its place.
                $newsRotates = $latestNews->count() > 3;
                $eventsRotate = $upcomingEvents->count() > 3;
            @endphp

            {{-- Both panels scroll.

                 They used to end in a "View all" link, which was the only way
                 to reach anything past the one story or three events on show.
                 That link left the site - and this is a single-page site, so
                 leaving it to read the news was the wrong shape. The panels now
                 hold everything they were given and let a visitor scroll
                 through it in place.

                 That is also why the rotation had to be rebuilt. It used to
                 stack the pages on top of one another and show one, which
                 needs the panel clipped, and a clipped panel cannot be
                 scrolled. Now the pages sit in normal flow in a real scrolling
                 box and the rotation moves the scroll position instead - same
                 3s, same easing, same upward direction, same 30s dwell. --}}
            <style>
                .marquee-scroll { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
                .marquee-scroll::-webkit-scrollbar { width: 6px; }
                .marquee-scroll::-webkit-scrollbar-track { background: transparent; }
                .marquee-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 9999px; }
                .marquee-scroll::-webkit-scrollbar-thumb:hover { background-color: #94a3b8; }
            </style>

            {{-- Latest news, three at a time - the same shape as the events
                 panel beside it.

                 It used to rotate one story at a time, which is why one panel
                 turned over three items and the other turned over one. Both
                 now chunk(3), so the two columns move in step.

                 Fitting three where one used to go is what drove the card down
                 to a fixed 96px with a square thumbnail: three of them plus the
                 12px gaps is exactly the 312px the events column stands at, so
                 the two panels end level rather than one trailing the other.
                 The card is a row at every width now, too - it used to stack
                 image-above-text on mobile, which made it 250px tall there and
                 would have put a group of three well past the fold.

                 The grouping is done here, in PHP, from whatever the database
                 returned. Nothing about the count is written into the
                 JavaScript: chunk(3) on four stories gives 3 + 1, on ten it
                 gives 3 + 3 + 3 + 1, and the last group is simply shorter.

                 Every story is in the page from the start, so a screen reader
                 or a search engine gets all of them whether or not the rotation
                 ever runs. --}}
            @php $newsGroups = $latestNews->chunk(3)->values(); @endphp

            <div
                id="news"
                class="scroll-mt-24"
                @if ($newsRotates)
                    x-data="marqueeList()"
                    x-on:mouseenter="hold()"
                    x-on:mouseleave="release()"
                @endif
            >
                {{-- White over the tinted photograph, the school's blue over
                     the plain background. The heading is the only text in this
                     column not sitting on a card of its own, so it is the only
                     one the ground beneath it can affect. --}}
                <h2 class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] {{ $newsEventsBackground ? 'edn-on-photo-brand' : 'text-primary-700' }}">Latest News</h2>

                @if ($newsRotates)
                    {{-- A fixed height is what makes this a page at a time
                         rather than a long column, and overscroll-contain stops
                         reaching the last story from carrying on and scrolling
                         the page underneath it.

                         328 is 312 of stories and the 16 of gap below them.
                         Without that gap the groups were flush, so the last
                         card of one page and the first of the next touched
                         edge to edge as they slid past each other. The track
                         has to carry the gap in its height or the group would
                         no longer fill a page. --}}
                    <div
                        x-ref="track"
                        x-on:scroll.passive="onScroll()"
                        x-on:touchstart.passive="hold()"
                        tabindex="0"
                        role="region"
                        aria-label="Latest news"
                        class="marquee-scroll relative mt-4 h-[328px] overflow-y-auto overscroll-contain pr-2"
                    >
                        @foreach ($newsGroups as $group)
                            {{-- The held height is what stops a short final
                                 group - one story where the last showed three -
                                 letting the group behind it creep into view. --}}
                            <div class="mb-4 min-h-[312px] space-y-3">
                                @foreach ($group as $post)
                                    <a
                                        href="{{ route('public.school-news.show', [$school, $post]) }}"
                                        class="edn-translucent-card group flex h-24 overflow-hidden rounded-[10px] border shadow-sm transition-shadow duration-300 ease-out hover:shadow-md"
                                    >
                                        <div class="h-full w-24 shrink-0 overflow-hidden bg-gray-100">
                                            @if ($post->imageUrl())
                                                <img src="{{ $post->imageUrl() }}" alt="" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center">
                                                    <i class="fa-regular fa-newspaper text-[20px] text-gray-400" aria-hidden="true"></i>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1 px-3 py-2.5">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                @if ($post->category)
                                                    <span class="rounded-[4px] bg-primary-700 px-1.5 py-0.5 text-[8.5px] font-bold uppercase tracking-wide text-white">{{ $post->category }}</span>
                                                @endif
                                                <span class="text-[10px] font-medium text-gray-600">{{ $post->published_at?->format('M j, Y') }}</span>
                                            </div>

                                            <p class="mt-1 line-clamp-2 text-[12.5px] font-bold leading-snug text-primary-700 group-hover:text-primary-800">{{ $post->title }}</p>

                                            @if ($post->excerpt)
                                                <p class="mt-0.5 line-clamp-1 text-[11px] text-gray-700">{{ $post->excerpt }}</p>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    {{-- One dot per GROUP, not per story, exactly as the events
                         panel does it. --}}
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($newsGroups as $groupIndex => $group)
                            <button
                                type="button"
                                x-on:click="show({{ $groupIndex }})"
                                aria-label="Show stories {{ $groupIndex * 3 + 1 }} to {{ $groupIndex * 3 + $group->count() }}"
                                class="h-1.5 rounded-full transition-all duration-500"
                                x-bind:class="i === {{ $groupIndex }} ? 'w-6 bg-primary-700' : 'w-1.5 bg-gray-300'"
                            ></button>
                        @endforeach
                    </div>
                @else
                    {{-- Three or fewer: the same cards, simply sitting still.
                         They used to fall into a three-across grid here, which
                         meant publishing a fourth story silently rearranged the
                         whole panel. Same card either way now. --}}
                    <div class="mt-4 space-y-3">
                        @forelse ($latestNews as $post)
                            <a
                                href="{{ route('public.school-news.show', [$school, $post]) }}"
                                class="edn-translucent-card group flex h-24 overflow-hidden rounded-[10px] border shadow-sm transition-shadow duration-300 ease-out hover:shadow-md"
                            >
                                <div class="h-full w-24 shrink-0 overflow-hidden bg-gray-100">
                                    @if ($post->imageUrl())
                                        <img src="{{ $post->imageUrl() }}" alt="" class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center">
                                            <i class="fa-regular fa-newspaper text-[20px] text-gray-400" aria-hidden="true"></i>
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1 px-3 py-2.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($post->category)
                                            <span class="rounded-[4px] bg-primary-700 px-1.5 py-0.5 text-[8.5px] font-bold uppercase tracking-wide text-white">{{ $post->category }}</span>
                                        @endif
                                        <span class="text-[10px] font-medium text-gray-600">{{ $post->published_at?->format('M j, Y') }}</span>
                                    </div>

                                    <p class="mt-1 line-clamp-2 text-[12.5px] font-bold leading-snug text-primary-700 group-hover:text-primary-800">{{ $post->title }}</p>

                                    @if ($post->excerpt)
                                        <p class="mt-0.5 line-clamp-1 text-[11px] text-gray-700">{{ $post->excerpt }}</p>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p class="rounded-[10px] border border-dashed border-gray-300 bg-white p-6 text-center text-[13px] font-medium text-gray-700">
                                No news has been published yet. Check back soon.
                            </p>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- Upcoming events, three at a time.

                 Each group of three rises over 3s, eased across the whole
                 distance rather than snapping at the end, and is held 30s - a
                 33s cycle.

                 The grouping is done here, in PHP, from whatever the database
                 returned. Nothing about the number of events is written into
                 the JavaScript: chunk(3) on four events gives 3 + 1, on ten it
                 gives 3 + 3 + 3 + 1, and the last group is simply shorter. The
                 rotation walks the groups it finds in the page.

                 The held height on each group is what stops a short final
                 group - one event where the last showed three - from letting
                 the next group creep up into view. --}}
            @php $eventGroups = $upcomingEvents->chunk(3)->values(); @endphp

            <div
                id="events"
                class="scroll-mt-24"
                @if ($eventsRotate)
                    x-data="marqueeList()"
                    x-on:mouseenter="hold()"
                    x-on:mouseleave="release()"
                @endif
            >
                <h2 class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] {{ $newsEventsBackground ? 'edn-on-photo-brand' : 'text-primary-700' }}">Upcoming Events</h2>

                @if ($eventsRotate)
                    <div
                        x-ref="track"
                        x-on:scroll.passive="onScroll()"
                        x-on:touchstart.passive="hold()"
                        tabindex="0"
                        role="region"
                        aria-label="Upcoming events"
                        class="marquee-scroll relative mt-4 h-[328px] overflow-y-auto overscroll-contain pr-2"
                    >
                        @foreach ($eventGroups as $group)
                            <div class="mb-4 min-h-[312px] space-y-3">
                                @foreach ($group as $event)
                                    <div class="edn-translucent-card flex items-start gap-3 rounded-[10px] border p-3.5 shadow-sm">
                                        <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-[8px] bg-primary-700 text-white">
                                            <span class="text-[15px] font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                                            <span class="text-[9px] font-bold uppercase leading-none">{{ $event->starts_at->format('M') }}</span>
                                        </span>

                                        <div class="min-w-0">
                                            <p class="text-[13px] font-bold text-gray-900">{{ $event->title }}</p>
                                            <p class="mt-1 flex items-center gap-1.5 text-[11.5px] text-gray-700">
                                                <i class="fa-regular fa-clock text-[11px] text-primary-700" aria-hidden="true"></i>
                                                {{ $event->starts_at->format('g:i A') }}@if ($event->ends_at) &ndash; {{ $event->ends_at->format('g:i A') }} @endif
                                            </p>
                                            @if ($event->location)
                                                <p class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-gray-700">
                                                    <i class="fa-solid fa-location-dot text-[11px] text-primary-700" aria-hidden="true"></i>
                                                    {{ $event->location }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    {{-- One dot per GROUP, not per event. Thirty seconds is a
                         long time to wait for the next three. --}}
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($eventGroups as $groupIndex => $group)
                            <button
                                type="button"
                                x-on:click="show({{ $groupIndex }})"
                                aria-label="Show events {{ $groupIndex * 3 + 1 }} to {{ $groupIndex * 3 + $group->count() }}"
                                class="h-1.5 rounded-full transition-all duration-500"
                                x-bind:class="i === {{ $groupIndex }} ? 'w-6 bg-primary-700' : 'w-1.5 bg-gray-300'"
                            ></button>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @forelse ($upcomingEvents as $event)
                            <div class="edn-translucent-card flex items-start gap-3 rounded-[10px] border p-3.5 shadow-sm">
                                <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-[8px] bg-primary-700 text-white">
                                    <span class="text-[15px] font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                                    <span class="text-[9px] font-bold uppercase leading-none">{{ $event->starts_at->format('M') }}</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-bold text-gray-900">{{ $event->title }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-[11.5px] text-gray-700">
                                        <i class="fa-regular fa-clock text-[11px] text-primary-700" aria-hidden="true"></i>
                                        {{ $event->starts_at->format('g:i A') }}@if ($event->ends_at) &ndash; {{ $event->ends_at->format('g:i A') }} @endif
                                    </p>
                                    @if ($event->location)
                                        <p class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-gray-700">
                                            <i class="fa-solid fa-location-dot text-[11px] text-primary-700" aria-hidden="true"></i>
                                            {{ $event->location }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-[10px] border border-dashed border-gray-300 bg-white p-6 text-center text-[13px] font-medium text-gray-700">
                                No events are scheduled at the moment.
                            </p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- SEGMENT 6 - facilities and gallery, side by side, both always here. --}}
    <section class="bg-white px-4 py-14 sm:px-6 lg:py-16">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-10">
            <div id="facilities" class="scroll-mt-24">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] text-primary-700">Our Facilities</h2>
                    <a href="{{ route('public.school-facilities.index', $school) }}" class="text-[12px] font-semibold text-primary-700 hover:text-primary-800">View all &rarr;</a>
                </div>

                {{-- Each facility gets an icon that means something. This row
                     used to print the same fa-school over every entry, so a
                     library, a swimming pool and a sick bay were indistinguish-
                     able and the icons said nothing at all.

                     There is no icon column on the table, so it is inferred
                     from the name - see [App\Support\FacilityIcon]. fa-fw keeps
                     them all the same width, which matters in a grid: a
                     droplet and a bus are very different shapes and without it
                     the labels below them sit ragged.

                     The row sits in its own bordered card, as the reference
                     draws it. The section behind it is white too, so the border
                     is the only thing holding the six together as one group -
                     without it they float loose on the page. --}}
                <div class="mt-5 rounded-[10px] border border-gray-200 bg-white px-5 py-6 shadow-sm">
                    <div class="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-6">
                        @forelse ($facilities as $facility)
                            <div class="text-center">
                                <i class="fa-solid {{ \App\Support\FacilityIcon::for($facility->name, $facility->category) }} fa-fw mx-auto block text-[30px] leading-none text-primary-700" aria-hidden="true"></i>
                                <p class="mt-3 text-[11px] font-bold leading-tight text-gray-900">{{ $facility->name }}</p>
                            </div>
                        @empty
                            <p class="col-span-3 py-2 text-center text-[13px] font-medium text-gray-700 sm:col-span-6">
                                Facilities have not been added yet.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Our gallery, three at a time - the same behaviour as the
                 events and news panels.

                 The tile itself is untouched: the same clipped, rounded, grey
                 frame and the same `transition-transform duration-500 ease-out
                 hover:scale-110` zoom it has always had. Only two things about
                 it changed, and both were forced by the ask. It sits three
                 across rather than six, because three at a time is the
                 requirement; and being twice as wide it is twice as tall, so
                 the photographs are not squashed into letterbox slots.

                 The grouping is done here, in PHP, from whatever the database
                 returned. Nothing about the count reaches the JavaScript:
                 chunk(3) on four images gives 3 + 1, on ten it gives
                 3 + 3 + 3 + 1, and the last group is simply shorter.

                 Every image is in the page from the start, so a screen reader
                 or a search engine gets all of them whether or not the rotation
                 ever runs. --}}
            @php
                $galleryRotates = $galleryImages->count() > 3;
                $galleryGroups = $galleryImages->chunk(3)->values();

                // The viewer is handed the WHOLE gallery, not the group on
                // show, so next and previous walk the entire set.
                $galleryPayload = $galleryImages
                    ->map(fn ($image) => ['src' => $image->imageUrl(), 'caption' => $image->caption])
                    ->values();
            @endphp

            <div id="gallery" class="scroll-mt-24" x-data="galleryViewer(@js($galleryPayload))">
                <h2 class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] text-primary-700">Our Gallery</h2>

                @if ($galleryRotates)
                    <div
                        class="mt-5"
                        x-data="marqueeList()"
                        x-on:mouseenter="hold()"
                        x-on:mouseleave="release()"
                    >
                        <div
                            x-ref="track"
                            x-on:scroll.passive="onScroll()"
                            x-on:touchstart.passive="hold()"
                            tabindex="0"
                            role="region"
                            aria-label="Our gallery"
                            class="marquee-scroll relative h-[144px] overflow-y-auto overscroll-contain pr-2"
                        >
                            @foreach ($galleryGroups as $groupIndex => $group)
                                {{-- The held height is what stops a short final
                                     group - one photograph where the last showed
                                     three - letting the group behind it creep
                                     up into view.

                                     The margin below is what keeps a gap
                                     between one row of photographs and the
                                     next; without it they met edge to edge
                                     mid-slide and read as one tall picture.
                                     The track is 144 - 128 of photograph and
                                     16 of gap - so a row still fills a page. --}}
                                <div class="mb-4 grid min-h-[128px] grid-cols-3 gap-4">
                                    @foreach ($group as $image)
                                        <button
                                            type="button"
                                            x-on:click="openAt({{ $groupIndex * 3 + $loop->index }}, $event)"
                                            aria-label="View photograph {{ $groupIndex * 3 + $loop->iteration }}{{ $image->caption ? ': '.$image->caption : '' }}"
                                            class="block h-[128px] overflow-hidden rounded-[8px] bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-700"
                                        >
                                            <img src="{{ $image->imageUrl() }}" alt="{{ $image->caption }}" class="h-full w-full object-cover transition-transform duration-500 ease-out hover:scale-110">
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        {{-- One dot per GROUP, as the other two panels do it. --}}
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($galleryGroups as $groupIndex => $group)
                                <button
                                    type="button"
                                    x-on:click="show({{ $groupIndex }})"
                                    aria-label="Show photographs {{ $groupIndex * 3 + 1 }} to {{ $groupIndex * 3 + $group->count() }}"
                                    class="h-1.5 rounded-full transition-all duration-500"
                                    x-bind:class="i === {{ $groupIndex }} ? 'w-6 bg-primary-700' : 'w-1.5 bg-gray-300'"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Three or fewer: all of them, sitting still. One image
                         keeps a tile the size of the others rather than
                         stretching across the panel. --}}
                    <div class="mt-5 grid grid-cols-3 gap-4">
                        @forelse ($galleryImages as $image)
                            <button
                                type="button"
                                x-on:click="openAt({{ $loop->index }}, $event)"
                                aria-label="View photograph {{ $loop->iteration }}{{ $image->caption ? ': '.$image->caption : '' }}"
                                class="block h-[128px] overflow-hidden rounded-[8px] bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-700"
                            >
                                <img src="{{ $image->imageUrl() }}" alt="{{ $image->caption }}" class="h-full w-full object-cover transition-transform duration-500 ease-out hover:scale-110">
                            </button>
                        @empty
                            <p class="col-span-3 rounded-[10px] border border-dashed border-gray-300 p-6 text-center text-[13px] font-medium text-gray-700">
                                No photographs have been added yet.
                            </p>
                        @endforelse
                    </div>
                @endif

                @if ($galleryImages->isNotEmpty())
                    {{-- The viewer. 70vw by 70vh on a desktop, near enough the
                         full width on a phone where 70% would be unreadable,
                         and the photograph is object-contain inside it so it is
                         never cropped or stretched to fit.

                         The controls sit outside the frame on a desktop - there
                         is 15vw of backdrop either side to put them in - and
                         come inside at the edges on small screens where there
                         is not. --}}
                    <div
                        x-cloak
                        x-show="isOpen"
                        x-transition:enter="transition-opacity ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        x-on:keydown.escape.window="close()"
                        x-on:keydown.arrow-right.window="isOpen && next()"
                        x-on:keydown.arrow-left.window="isOpen && previous()"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Gallery image viewer"
                        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                    >
                        <div class="absolute inset-0 bg-black/80" x-on:click="close()"></div>

                        <button
                            type="button"
                            x-ref="closeButton"
                            x-on:click="close()"
                            aria-label="Close viewer"
                            class="absolute right-4 top-4 z-10 flex h-11 w-11 items-center justify-center edn-viewer-control rounded-full text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        >
                            <i class="fa-solid fa-xmark text-[19px]" aria-hidden="true"></i>
                        </button>

                        <div
                            x-show="isOpen"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative flex h-[70vh] w-[92vw] flex-col sm:w-[70vw]"
                        >
                            <img
                                x-bind:src="current?.src"
                                x-bind:alt="current?.caption ?? ''"
                                class="min-h-0 w-full flex-1 object-contain"
                            >

                            <p class="mt-3 shrink-0 text-center text-[12.5px] text-white/85">
                                <span x-text="current?.caption ?? ''"></span>
                                <span class="ml-2 text-white/60" x-text="`${index + 1} / ${images.length}`"></span>
                            </p>

                            <button
                                type="button"
                                x-on:click="previous()"
                                x-bind:disabled="! hasPrevious"
                                aria-label="Previous image"
                                class="absolute left-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center edn-viewer-control rounded-full text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white disabled:pointer-events-none disabled:opacity-25 sm:-left-16"
                            >
                                <i class="fa-solid fa-chevron-left text-[18px]" aria-hidden="true"></i>
                            </button>

                            <button
                                type="button"
                                x-on:click="next()"
                                x-bind:disabled="! hasNext"
                                aria-label="Next image"
                                class="absolute right-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center edn-viewer-control rounded-full text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white disabled:pointer-events-none disabled:opacity-25 sm:-right-16"
                            >
                                <i class="fa-solid fa-chevron-right text-[18px]" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>


    {{-- SEGMENT 6b - the Principal's Desk, and the school's own words.

         Restored, and placed here on purpose: below Events, Our Facilities and
         Our Gallery, and above Contact. It reads as the school speaking for
         itself after a visitor has seen what the school actually does, rather
         than a statement of intent before any of it.

         Every block is optional and the whole band disappears when a school
         has filled none of them in - a "From the Principal's Desk" heading
         over an empty box is worse than no section. --}}
    @php
        $hasPrincipal = filled($website->principal_message) || filled($website->principal_name);
        $hasQuote = filled($website->quote_text);
        $hasEthos = filled($website->mission) || filled($website->vision) || filled($website->values);
    @endphp

    @if ($hasPrincipal || $hasQuote || $hasEthos)
        <section id="principal" class="scroll-mt-24 bg-white px-4 py-14 sm:px-6 lg:py-16">
            <div class="mx-auto max-w-7xl">
                <p class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] text-primary-700">Our School</p>
                <h2 class="edn-reveal mt-2 text-[26px] font-extrabold leading-tight tracking-tight text-gray-900 sm:text-[28px]" style="--edn-delay: 110ms;">
                    From the Principal&rsquo;s Desk
                </h2>
                <span class="mt-3 block rounded-full bg-primary-700" style="width: 48px; height: 3px;"></span>

                <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @if ($hasPrincipal)
                        <div class="edn-reveal overflow-hidden rounded-[10px] border border-gray-200 lg:col-span-2" style="--edn-delay: 160ms;">
                            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start">
                                @if ($website->principalPhotoUrl())
                                    <div class="h-40 w-full shrink-0 overflow-hidden rounded-[8px] sm:h-44 sm:w-36">
                                        <img
                                            src="{{ $website->principalPhotoUrl() }}"
                                            alt="{{ $website->principal_name ?: 'The Principal' }}"
                                            class="h-full w-full object-cover"
                                        >
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <i class="fa-solid fa-quote-left text-[20px] text-primary-200" aria-hidden="true"></i>

                                    @if (filled($website->principal_message))
                                        <p class="mt-2 whitespace-pre-line text-[14.5px] leading-relaxed text-gray-700">{{ $website->principal_message }}</p>
                                    @endif

                                    @if (filled($website->principal_name))
                                        <p class="mt-4 text-[13px] font-extrabold text-gray-900">{{ $website->principal_name }}</p>
                                        <p class="text-[11.5px] font-semibold text-primary-700">{{ $website->principal_title ?: 'Principal' }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($hasQuote)
                        <div class="edn-reveal flex flex-col justify-between rounded-[10px] border border-amber-200 bg-amber-50 p-6" style="--edn-delay: 220ms;">
                            <div>
                                <p class="text-[10.5px] font-bold uppercase tracking-[0.16em] text-amber-700">Quote of the Week</p>
                                <p class="mt-3 text-[15px] font-semibold italic leading-relaxed text-gray-800">&ldquo;{{ $website->quote_text }}&rdquo;</p>
                            </div>

                            @if (filled($website->quote_author))
                                <div class="mt-4">
                                    <p class="text-[12.5px] font-bold text-gray-900">&mdash; {{ $website->quote_author }}</p>
                                    @if (filled($website->quote_author_role))
                                        <p class="text-[11px] text-gray-600">{{ $website->quote_author_role }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Mission, Vision and Values. Editable all along, under the
                     About Us tab, and shown nowhere on the home page. --}}
                @if ($hasEthos)
                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['label' => 'Our Mission', 'icon' => 'fa-bullseye', 'body' => $website->mission],
                            ['label' => 'Our Vision', 'icon' => 'fa-eye', 'body' => $website->vision],
                            ['label' => 'Our Values', 'icon' => 'fa-heart', 'body' => $website->values],
                        ] as $index => $statement)
                            @if (filled($statement['body']))
                                <div class="edn-reveal rounded-[10px] border border-gray-200 p-6" style="--edn-delay: {{ 260 + ($index * 60) }}ms;">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-[8px] bg-primary-50 text-primary-700">
                                        <i class="fa-solid {{ $statement['icon'] }}" aria-hidden="true"></i>
                                    </span>
                                    <h3 class="mt-3 text-[14px] font-extrabold text-gray-900">{{ $statement['label'] }}</h3>
                                    <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-gray-700">{{ $statement['body'] }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- SEGMENT 7 - get in touch, and report a concern.

         Four columns as the reference draws them: the school's details, a
         photograph of the building, the form, and a map.

         The middle panel carries TWO forms behind a toggle. The first is the
         ordinary contact form. The second is for a member of the public who
         has seen a pupil behaving badly outside school - a neighbour, a
         shopkeeper, a bus driver.

         That second form asks for as little as it can: a name, what happened,
         and whatever they photographed. No email, no telephone, no pupil to
         identify. Demanding contact details from somebody reporting a child's
         conduct is how you get no reports at all, and working out which child
         it was is the school's job, not the reporter's. --}}
    @php
        $siteContact = \App\Support\SchoolContact::for($school);
        $siteSocials = \App\Support\SchoolSocialLinks::for($school);
        // The About photo, or failing that WHATEVER THE HERO IS ACTUALLY
        // SHOWING - the first slide when the school has slides, the single
        // hero image when it has not.
        //
        // This used to read heroImageUrl() directly, which meant a school that
        // uploaded one hero image and later replaced it with a set of slides
        // still had that first, now-unused photo appear down here. The image
        // had been retired from the only place it was ever visible and then
        // quietly resurfaced somewhere the school never put it.
        $contactPhoto = $website->aboutImageUrl() ?: ($slides[0] ?? null);
        // The map reads the address itself, in <x-school-map>. It is not
        // built here any more, and deliberately no longer falls back to the
        // school's NAME: that produced a map of wherever Google decided the
        // name was, which on a school's own website is worse than no map.
    @endphp

    <section id="contact" class="scroll-mt-24 bg-white px-4 py-14 sm:px-6 lg:py-16">
        <div class="mx-auto max-w-7xl">
            <p class="edn-reveal text-[11.5px] font-bold uppercase tracking-[0.18em] text-primary-700">Get In Touch</p>
            <h2 class="edn-reveal mt-2 text-[26px] font-extrabold leading-tight tracking-tight text-gray-900 sm:text-[28px]" style="--edn-delay: 110ms;">Contact Us</h2>
            <span class="mt-3 block rounded-full bg-primary-700" style="width: 48px; height: 3px;"></span>

            @if (session('contact_status') || session('misconduct_status'))
                <p class="mt-6 rounded-[10px] border border-green-200 bg-green-50 px-4 py-3 text-[13px] font-semibold text-green-800">
                    {{ session('contact_status') ?: session('misconduct_status') }}
                </p>
            @endif

            <div class="mt-7 grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                {{-- 1. The details. --}}
                <div class="space-y-4 lg:col-span-3">
                    @foreach ([
                        ['fa-location-dot', $siteContact->address],
                        ['fa-phone', $siteContact->phone],
                        ['fa-envelope', $siteContact->email],
                        ['fa-clock', 'Mon - Fri: 8:00 AM - 4:00 PM'],
                    ] as [$icon, $value])
                        @continue (blank($value))
                        <p class="flex items-start gap-3 text-[13px] leading-relaxed text-gray-900">
                            <span class="mt-0.5 flex shrink-0 items-center justify-center rounded-full bg-primary-700 text-white" style="width: 24px; height: 24px;">
                                <i class="fa-solid {{ $icon }} text-[11px]" aria-hidden="true"></i>
                            </span>
                            <span class="min-w-0 font-medium">{{ $value }}</span>
                        </p>
                    @endforeach

                    @if ($siteSocials !== [])
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            @foreach ($siteSocials as $social)
                                <a
                                    href="{{ $social['url'] }}"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="{{ $social['label'] }}"
                                    class="flex items-center justify-center rounded-full bg-gray-100 text-gray-700 transition-colors duration-200 hover:bg-primary-700 hover:text-white"
                                    style="width: 34px; height: 34px;"
                                >
                                    <i class="{{ $social['icon'] }} text-[13px]" aria-hidden="true"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 2. The building. --}}
                <div class="hidden lg:col-span-2 lg:block">
                    @if ($contactPhoto)
                        <img src="{{ $contactPhoto }}" alt="{{ $school->name }}" class="w-full rounded-[10px] object-cover" style="aspect-ratio: 1 / 1;">
                    @else
                        <div class="flex w-full items-center justify-center rounded-[10px] bg-gray-100" style="aspect-ratio: 1 / 1;">
                            <i class="fa-solid fa-school text-[34px] text-gray-500" aria-hidden="true"></i>
                        </div>
                    @endif
                </div>

                {{-- 3. The forms, behind a toggle. --}}
                <div class="lg:col-span-4" x-data="{ mode: 'contact' }">
                    <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-4 grid grid-cols-2 gap-1 rounded-[8px] bg-gray-100 p-1">
                            @foreach ([['contact', 'Contact Us', 'fa-envelope'], ['report', 'Report a Concern', 'fa-triangle-exclamation']] as [$key, $label, $icon])
                                <button
                                    type="button"
                                    x-on:click="mode = @js($key)"
                                    x-bind:class="mode === @js($key) ? 'bg-white text-primary-700 shadow-sm' : 'text-gray-700 hover:text-gray-900'"
                                    class="flex items-center justify-center gap-1.5 rounded-[6px] px-2 py-2 text-[12px] font-bold transition-all duration-200"
                                >
                                    <i class="fa-solid {{ $icon }} text-[11px]" aria-hidden="true"></i>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Contact --}}
                        <form method="POST" action="{{ $school->publicUrl('public.contact-message.store') }}" x-show="mode === 'contact'">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="contact-name" class="block text-[12px] font-bold text-gray-900">Full Name</label>
                                    <input id="contact-name" name="name" type="text" required maxlength="120" class="mt-1.5 w-full" placeholder="Your full name" value="{{ old('name') }}">
                                </div>
                                <div>
                                    <label for="contact-email" class="block text-[12px] font-bold text-gray-900">Email Address</label>
                                    <input id="contact-email" name="email" type="email" class="mt-1.5 w-full" placeholder="Your email address">
                                </div>
                                <div>
                                    <label for="contact-phone" class="block text-[12px] font-bold text-gray-900">Phone Number</label>
                                    <input id="contact-phone" name="phone" type="tel" class="mt-1.5 w-full" placeholder="Your phone number">
                                </div>
                                <div>
                                    <label for="contact-subject" class="block text-[12px] font-bold text-gray-900">Subject</label>
                                    <input id="contact-subject" name="subject" type="text" class="mt-1.5 w-full" placeholder="Select subject">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="contact-message" class="block text-[12px] font-bold text-gray-900">Message</label>
                                <textarea id="contact-message" name="message" rows="3" required maxlength="2000" class="mt-1.5 w-full" placeholder="Your message here...">{{ old('message') }}</textarea>
                            </div>

                            <button type="submit" class="mt-4 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-700 px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-300 ease-out hover:bg-primary-800">
                                Send Message
                                <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                            </button>
                        </form>

                        {{-- Report a concern --}}
                        <form
                            method="POST"
                            action="{{ $school->publicUrl('public.misconduct-report.store') }}"
                            enctype="multipart/form-data"
                            x-show="mode === 'report'"
                            x-cloak
                            x-data="reportEvidence({{ \App\Http\Controllers\PublicMisconductReportController::minFiles() }}, {{ \App\Http\Controllers\PublicMisconductReportController::maxFiles() }}, {{ \App\Http\Controllers\PublicMisconductReportController::maxBytes() }})"
                        >
                            @csrf

                            <p class="rounded-[8px] bg-amber-50 px-3 py-2 text-[11.5px] leading-relaxed text-amber-900">
                                For reporting a pupil&rsquo;s conduct <strong>outside school</strong>. You only need to give
                                your name &mdash; the school will look into the rest.
                            </p>

                            <div class="mt-3">
                                <label for="report-name" class="block text-[12px] font-bold text-gray-900">Your Name</label>
                                <input id="report-name" name="reporter_name" type="text" required maxlength="120" class="mt-1.5 w-full" placeholder="Your full name" value="{{ old('reporter_name') }}">
                                @error('reporter_name')<p class="mt-1 text-[11.5px] font-semibold text-red-700">{{ $message }}</p>@enderror
                            </div>

                            {{-- Optional. It gives the school's inbox a line to
                                 list the report under, but nobody is made to
                                 summarise what they saw before they are allowed
                                 to write it down. --}}
                            <div>
                                <label for="report-subject" class="field-label">What is this about? <span class="font-normal text-gray-600">(optional)</span></label>
                                <input id="report-subject" name="subject" type="text" maxlength="180" class="mt-1.5 w-full" placeholder="e.g. Fighting at the bus stop" value="{{ old('subject') }}">
                                @error('subject')<p class="mt-1 text-[11.5px] font-semibold text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div class="mt-3">
                                <label for="report-location" class="block text-[12px] font-bold text-gray-900">
                                    Where did it happen? <span class="font-normal text-gray-700">(optional)</span>
                                </label>
                                <input id="report-location" name="location" type="text" maxlength="180" class="mt-1.5 w-full" placeholder="e.g. Ogui Road bus stop" value="{{ old('location') }}">
                            </div>

                            <div class="mt-3">
                                <label for="report-description" class="block text-[12px] font-bold text-gray-900">What did you see?</label>
                                <textarea id="report-description" name="description" rows="3" required maxlength="2000" class="mt-1.5 w-full" placeholder="Describe what happened, and when.">{{ old('description') }}</textarea>
                                @error('description')<p class="mt-1 text-[11.5px] font-semibold text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div class="mt-3">
                                <label for="report-attachments" class="block text-[12px] font-bold text-gray-900">
                                    Photographs <span class="font-normal text-red-700">(required)</span>
                                </label>

                                {{-- The real input is hidden and driven by the
                                     button below. A file input cannot have
                                     items removed from its own FileList, so
                                     the selection is held in Alpine and
                                     written back through a DataTransfer -
                                     which is what makes "remove this one"
                                     possible at all. --}}
                                <input
                                    id="report-attachments"
                                    x-ref="input"
                                    name="attachments[]"
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
                                    class="sr-only"
                                    x-on:change="add($event.target.files)"
                                >

                                <button
                                    type="button"
                                    x-on:click="$refs.input.click()"
                                    x-bind:disabled="files.length >= max"
                                    class="mt-1.5 flex w-full items-center justify-center gap-2 rounded-[8px] border border-dashed border-gray-400 px-3 py-3 text-[12px] font-bold text-gray-700 transition hover:border-primary-500 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-camera text-[13px]" aria-hidden="true"></i>
                                    <span x-text="files.length ? 'Add more photographs' : 'Choose photographs'"></span>
                                </button>

                                <small class="mt-1 block text-[11px] leading-relaxed text-gray-700">
                                    At least {{ \App\Http\Controllers\PublicMisconductReportController::minFiles() }},
                                    up to {{ \App\Http\Controllers\PublicMisconductReportController::maxFiles() }} files.
                                    Each must be 5MB or smaller &mdash; about two minutes of video.
                                    You can pick several at once, and remove any before sending.
                                </small>

                                {{-- Previews. A reporter photographing on a
                                     phone cannot otherwise tell which shots
                                     they picked, and the one thing worse than
                                     no evidence is the wrong photograph. --}}
                                <div x-show="files.length" x-cloak class="mt-2 grid grid-cols-4 gap-2">
                                    <template x-for="(item, index) in files" :key="item.id">
                                        <div class="relative overflow-hidden rounded-[6px] border border-gray-200">
                                            <template x-if="item.preview">
                                                <img :src="item.preview" alt="" class="h-16 w-full object-cover">
                                            </template>
                                            <template x-if="! item.preview">
                                                <span class="flex h-16 w-full items-center justify-center bg-gray-50 text-gray-500">
                                                    <i class="fa-solid fa-film text-[15px]" aria-hidden="true"></i>
                                                </span>
                                            </template>

                                            <button
                                                type="button"
                                                x-on:click="remove(index)"
                                                class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-white/90 text-red-600 shadow-sm hover:bg-white"
                                                x-bind:aria-label="'Remove ' + item.name"
                                            >
                                                <i class="fa-solid fa-xmark text-[10px]" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                <small x-show="files.length" x-cloak class="mt-1 block text-[11px] text-gray-600">
                                    <span x-text="files.length"></span> of <span x-text="max"></span> selected.
                                </small>

                                {{-- Said before they press send. The server
                                     refuses anyway, but finding that out after
                                     a slow upload on a phone is a reason to
                                     give up. --}}
                                <template x-for="message in problems" :key="message">
                                    <small class="mt-1 block text-[11.5px] font-semibold text-red-700" x-text="message"></small>
                                </template>

                                @error('attachments')<small class="mt-1 block text-[11.5px] font-semibold text-red-700">{{ $message }}</small>@enderror
                                @error('attachments.*')<small class="mt-1 block text-[11.5px] font-semibold text-red-700">{{ $message }}</small>@enderror
                            </div>

                            {{-- Disabled until the evidence rules are met. The
                                 server enforces them regardless - this only
                                 saves a reporter from a failed upload. --}}
                            <button
                                type="submit"
                                x-bind:disabled="! canSubmit"
                                class="mt-4 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-700 px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-300 ease-out hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Send Report
                                <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- 4. The map, drawn from the school's own address.

                     One component, shared with the Contact Us page, so the two
                     cannot disagree about where the school is. --}}
                <div class="lg:col-span-3">
                    <x-school-map :school="$school" :height="260" />
                </div>
            </div>
        </div>
    </section>
</x-public-site-layout>
