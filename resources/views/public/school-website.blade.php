@php
    $slides = $heroSlides->isNotEmpty()
        ? $heroSlides->map->imageUrl()->filter()->values()->all()
        : array_values(array_filter([$website->heroImageUrl()]));
    $levelColors = [
        'amber' => ['badge' => 'bg-amber-500', 'dot' => 'bg-amber-500', 'from' => 'from-amber-400', 'to' => 'to-amber-600'],
        'green' => ['badge' => 'bg-green-600', 'dot' => 'bg-green-600', 'from' => 'from-green-500', 'to' => 'to-green-700'],
        'purple' => ['badge' => 'bg-purple-600', 'dot' => 'bg-purple-600', 'from' => 'from-purple-500', 'to' => 'to-purple-700'],
        'blue' => ['badge' => 'bg-primary-600', 'dot' => 'bg-primary-600', 'from' => 'from-primary-500', 'to' => 'to-primary-700'],
    ];
    $blocksIcon = 'M4 14h5v6H4z M14 14h5v6h-5z M9.5 4h5v6h-5z';
    $pencilIcon = 'M4.5 19.5l1-4L15.5 5.5l3 3L8.5 18.5l-4 1z M13.5 6.5l3 3';
    $scaleIcon = 'M12 3v18M5.5 7l-3 6a3 3 0 006 0l-3-6zM18.5 7l-3 6a3 3 0 006 0l-3-6zM5 7h14M8.5 3h7';
    $capIcon = 'M12 3.5L2.5 8.3 12 13l9.5-4.7L12 3.5z M6 10.7v4.3c0 1.4 2.7 3 6 3s6-1.6 6-3v-4.3';
    $levelBlurbs = [
        'creche' => ['age' => '0-2 YRS', 'ageLabel' => 'Ages 0 – 2 Years', 'color' => 'amber', 'icon' => $blocksIcon, 'points' => ['Nurturing care', 'Sensory play', 'Early bonding']],
        'nursery' => ['age' => '2-4 YRS', 'ageLabel' => 'Ages 2 – 4 Years', 'color' => 'amber', 'icon' => $blocksIcon, 'points' => ['Play-based learning', 'Motor skills development', 'Building confidence']],
        'kindergarten' => ['age' => '4-5 YRS', 'ageLabel' => 'Ages 4 – 5 Years', 'color' => 'amber', 'icon' => $blocksIcon, 'points' => ['Pre-literacy & numeracy', 'Social skills', 'Creative play']],
        'primary' => ['age' => '5-11 YRS', 'ageLabel' => 'Ages 5 – 11 Years', 'color' => 'green', 'icon' => $pencilIcon, 'points' => ['Strong foundation', 'Curiosity & creativity', 'Academic excellence']],
        'jss' => ['age' => '11-14 YRS', 'ageLabel' => 'Ages 11 – 14 Years', 'color' => 'purple', 'icon' => $scaleIcon, 'points' => ['Critical thinking', 'Leadership skills', 'Personal growth']],
        'sss' => ['age' => '14-18 YRS', 'ageLabel' => 'Ages 14 – 18 Years', 'color' => 'blue', 'icon' => $capIcon, 'points' => ['University preparation', 'Career guidance', 'Global readiness']],
    ];
    $levelKey = function (string $name) {
        $lower = strtolower($name);
        return match (true) {
            str_contains($lower, 'creche') => 'creche',
            str_contains($lower, 'nursery') => 'nursery',
            str_contains($lower, 'kindergarten') || str_contains($lower, 'kg') => 'kindergarten',
            str_contains($lower, 'primary') => 'primary',
            str_contains($lower, 'jss') || str_contains($lower, 'junior') => 'jss',
            default => 'sss',
        };
    };
@endphp

@php
    $homeBlocks = $school->websiteBlocksFor('home')->groupBy('section');
@endphp

<x-public-site-layout :school="$school" :website="$website" :used-fonts="\App\Support\GoogleFonts::usedInBlocks($homeBlocks->flatten(1))">
    {{-- Hero --}}
    <section id="hero-{{ $website->uuid }}" class="relative min-h-[600px] overflow-hidden bg-gray-900 sm:min-h-[620px] lg:min-h-[650px]" x-data="{ slide: 0, total: {{ max(count($slides), 1) }} }" x-init="total > 1 && setInterval(() => slide = (slide + 1) % total, 5000)">
        @forelse ($slides as $index => $slideUrl)
            <div
                class="edn-kenburns absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
                style="background-image: url('{{ $slideUrl }}'); animation-delay: {{ $index * -3 }}s"
                x-show="slide === {{ $index }}"
                x-transition:enter="transition-opacity duration-1000"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            ></div>
        @empty
        @endforelse

        <div class="relative flex min-h-[600px] items-center sm:min-h-[620px] lg:min-h-[650px]">
            <div class="mx-auto grid w-full max-w-7xl grid-cols-1 gap-8 px-4 py-16 sm:px-6 lg:grid-cols-3 lg:py-24">
                <div class="lg:col-span-2">
                    <x-website-blocks :blocks="$homeBlocks->get('hero', collect())" height="480px" />

                    @if (count($slides) > 1)
                        <div class="edn-enter-right mt-6 flex gap-2" style="animation-delay: 500ms">
                            @foreach ($slides as $index => $slideUrl)
                                <button type="button" @click="slide = {{ $index }}" class="h-2 rounded-full transition-all duration-300" :class="slide === {{ $index }} ? 'w-6 bg-white' : 'w-2 bg-white/40'"></button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($website->show_whats_happening && $upcomingEvents->isNotEmpty())
                    <div class="edn-enter-right rounded-[10px] border border-white/25 bg-white/15 p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl" style="animation-delay: 550ms">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold text-white drop-shadow-sm">{{ $website->whats_happening_title ?: "What's Happening" }}</h2>
                            <a href="{{ route('public.school-events.index', $school) }}" class="text-xs font-semibold text-white/90 transition-colors duration-200 hover:text-white">View All</a>
                        </div>
                        <div class="mt-3 space-y-3">
                            @foreach ($upcomingEvents->take(3) as $event)
                                <a href="{{ route('public.school-events.index', $school) }}" class="flex items-start gap-3 rounded-[8px] p-2 transition-colors duration-150 hover:bg-white/10">
                                    <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-[8px] bg-white/90 text-primary-700">
                                        <span class="text-[9px] font-bold uppercase leading-none">{{ $event->starts_at->format('M') }}</span>
                                        <span class="text-sm font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-white drop-shadow-sm">{{ $event->title }}</span>
                                        <span class="block text-xs text-white/75">{{ $event->starts_at->format('M j, Y') }}@if (! $event->is_all_day) &middot; {{ $event->starts_at->format('g:ia') }} @endif</span>
                                    </span>
                                    <svg class="mt-1 h-4 w-4 shrink-0 text-white/50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </a>
                            @endforeach
                        </div>
                        <a href="{{ route('public.school-events.index', $school) }}" class="mt-4 flex items-center justify-center gap-1.5 rounded-[8px] border border-white/30 py-2.5 text-sm font-semibold text-white transition-colors duration-200 hover:bg-white/10">
                            View All Events
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Feature icons: floating card overlapping the hero --}}
    <section class="relative z-10 -mt-14 px-4 sm:-mt-16 sm:px-6">
        <div class="mx-auto max-w-6xl">
            <div class="edn-enter rounded-[20px] bg-white p-6 shadow-2xl shadow-black/10 sm:p-8" style="animation-delay: 600ms">
                <x-website-blocks :blocks="$homeBlocks->get('feature-icons', collect())" height="220px" />
            </div>
        </div>
    </section>

    {{-- Stats banner: floating card with decorative bookend icons --}}
    @if ($homeBlocks->has('stats'))
        <section class="px-4 pt-8 sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="relative overflow-hidden rounded-[20px] bg-gradient-to-r from-primary-600 to-primary-800 px-6 py-8 shadow-xl sm:px-16 sm:py-10">
                    <svg class="pointer-events-none absolute left-3 top-1/2 hidden h-16 w-16 -translate-y-1/2 text-amber-300/90 drop-shadow-lg sm:block lg:left-6 lg:h-20 lg:w-20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 4h10v4a5 5 0 01-5 5 5 5 0 01-5-5V4z" fill="currentColor" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        <path d="M7 4H4a1 1 0 00-1 1v1a4 4 0 004 4M17 4h3a1 1 0 011 1v1a4 4 0 01-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                        <path d="M12 13v4m-4 4h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <svg class="pointer-events-none absolute -left-2 top-3 hidden h-3 w-3 text-pink-300 sm:block" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" /></svg>
                    <svg class="pointer-events-none absolute left-14 bottom-4 hidden h-2.5 w-2.5 text-cyan-300 sm:block lg:left-20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" /></svg>

                    <svg class="pointer-events-none absolute right-3 top-1/2 hidden h-16 w-16 -translate-y-1/2 text-white/85 drop-shadow-lg sm:block lg:right-6 lg:h-20 lg:w-20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2.5c2.2 2 3.3 5 3.3 8.3 0 2.2-1.1 4.3-3.3 6.2-2.2-1.9-3.3-4-3.3-6.2 0-3.3 1.1-6.3 3.3-8.3z" fill="currentColor" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" />
                        <path d="M9 13.5l-2.8 2.8.7 2.8 2.8-.7M15 13.5l2.8 2.8-.7 2.8-2.8-.7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="9" r="1.3" fill="#1259bd" />
                    </svg>
                    <svg class="pointer-events-none absolute right-1 top-4 hidden h-3 w-3 text-yellow-300 sm:block" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" /></svg>
                    <svg class="pointer-events-none absolute right-16 bottom-3 hidden h-2.5 w-2.5 text-pink-200 sm:block lg:right-24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" /></svg>

                    <div class="relative">
                        <x-website-blocks :blocks="$homeBlocks->get('stats')" height="110px" />
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Academic levels --}}
    @if ($academicLevels->isNotEmpty())
        <section id="academics" class="scroll-mt-20 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <div class="text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Academic Levels</p>
                    <h2 class="mt-2 text-2xl font-extrabold text-gray-900 sm:text-3xl">Education for Every Stage of Growth</h2>
                    <p class="mt-2 text-sm text-gray-500">From nurturing young minds to preparing future leaders.</p>
                </div>

                <div class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($academicLevels as $level)
                        @php
                            $blurb = $levelBlurbs[$levelKey($level->name)];
                            $colors = $levelColors[$blurb['color']];
                        @endphp
                        <div class="group overflow-hidden rounded-[14px] border border-gray-200 bg-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-xl">
                            <div class="relative flex h-36 items-center justify-center overflow-hidden bg-gradient-to-br {{ $colors['from'] }} {{ $colors['to'] }}">
                                <svg class="h-16 w-16 text-white/20 transition-transform duration-500 ease-out group-hover:scale-110" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="{{ $blurb['icon'] }}" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <span class="absolute left-3 top-3 flex h-9 w-9 items-center justify-center rounded-[10px] {{ $colors['badge'] }} text-white shadow-md">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="{{ $blurb['icon'] }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </span>
                                <span class="absolute right-3 top-3 rounded-full {{ $colors['badge'] }} px-2.5 py-1 text-[10px] font-bold text-white shadow-md">{{ $blurb['age'] }}</span>
                            </div>
                            <div class="p-5">
                                <h3 class="text-base font-bold text-gray-900">{{ $level->name }}</h3>
                                <p class="text-xs text-gray-500">{{ $blurb['ageLabel'] }}</p>
                                <ul class="mt-3 space-y-1.5">
                                    @foreach ($blurb['points'] as $point)
                                        <li class="flex items-center gap-1.5 text-xs text-gray-600">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $colors['dot'] }}"></span>
                                            {{ $point }}
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('public.school-admissions.index', $school) }}" class="mt-4 inline-flex items-center gap-1 text-xs font-bold text-primary-600 transition-colors duration-200 hover:text-primary-700">
                                    Learn More
                                    <svg class="h-3 w-3 transition-transform duration-200 group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Meet our teachers + Principal's desk + Quote of the week --}}
    @if ($teachers->isNotEmpty() || $homeBlocks->has('principal') || $homeBlocks->has('quote'))
        @php
            $teacherItems = $teachers->map(fn ($t) => [
                'name' => $t->fullName(),
                'dept' => $t->department ?? 'Teacher',
                'photo' => $t->photoUrl(),
                'initials' => Str::of($t->first_name)->substr(0, 1).Str::of($t->last_name)->substr(0, 1),
            ])->values()->all();
            $teacherCloneCount = min(count($teacherItems), 6);
            $teacherTrack = $teacherItems ? [
                ...array_slice($teacherItems, -$teacherCloneCount),
                ...$teacherItems,
                ...array_slice($teacherItems, 0, $teacherCloneCount),
            ] : [];
        @endphp
        <section class="border-t border-gray-100 bg-white px-4 py-16 sm:px-6">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-10">
                @if ($teachers->isNotEmpty())
                    <div
                        x-data="{
                            shown: false,
                            index: {{ $teacherCloneCount }},
                            cloneCount: {{ $teacherCloneCount }},
                            total: {{ count($teacherItems) }},
                            step: 112,
                            noTransition: false,
                            timer: null,
                            init() {
                                const observer = new IntersectionObserver((entries) => {
                                    entries.forEach((entry) => { if (entry.isIntersecting) { this.shown = true; observer.disconnect(); } });
                                }, { threshold: 0.15 });
                                observer.observe(this.$el);
                                this.restart();
                            },
                            restart() {
                                clearInterval(this.timer);
                                this.timer = setInterval(() => this.stepNext(), 2000);
                            },
                            stepNext() {
                                this.index++;
                                if (this.index >= this.cloneCount + this.total) {
                                    setTimeout(() => {
                                        this.noTransition = true;
                                        this.index = this.cloneCount;
                                        requestAnimationFrame(() => requestAnimationFrame(() => { this.noTransition = false; }));
                                    }, 700);
                                }
                            },
                            stepPrev() {
                                this.index--;
                                if (this.index < this.cloneCount) {
                                    setTimeout(() => {
                                        this.noTransition = true;
                                        this.index = this.cloneCount + this.total - 1;
                                        requestAnimationFrame(() => requestAnimationFrame(() => { this.noTransition = false; }));
                                    }, 700);
                                }
                            },
                            goNext() { this.stepNext(); this.restart(); },
                            goPrev() { this.stepPrev(); this.restart(); },
                        }"
                        :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-10'"
                        class="rounded-[16px] bg-gray-50 p-6 transition-all duration-[900ms] ease-out lg:col-span-4"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Meet Our Amazing Teachers</p>
                        <h2 class="mt-1 text-lg font-extrabold text-gray-900">Experienced. Dedicated. Inspiring.</h2>

                        <div class="relative mx-auto mt-6 w-full max-w-[432px]" @mouseenter="clearInterval(timer)" @mouseleave="restart()">
                            <div class="overflow-hidden">
                                <div
                                    class="flex gap-4"
                                    :class="noTransition ? '' : 'transition-transform duration-700 ease-out'"
                                    :style="'transform: translateX(-' + (index * step) + 'px)'"
                                >
                                    @foreach ($teacherTrack as $teacher)
                                        <div class="w-24 shrink-0 text-center">
                                            <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-primary-100">
                                                @if ($teacher['photo'])
                                                    <img src="{{ $teacher['photo'] }}" class="h-full w-full object-cover" alt="{{ $teacher['name'] }}">
                                                @else
                                                    <span class="text-lg font-extrabold text-primary-700">{{ $teacher['initials'] }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-2 truncate text-xs font-bold text-gray-900">{{ $teacher['name'] }}</p>
                                            <p class="truncate text-[10px] text-gray-500">{{ $teacher['dept'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            @if (count($teacherItems) > 1)
                                <button
                                    type="button"
                                    @click="goPrev()"
                                    aria-label="Previous teacher"
                                    class="absolute -left-3 top-8 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-700 shadow-md ring-1 ring-gray-200 transition-all duration-200 ease-out hover:scale-110 hover:text-primary-600 hover:shadow-lg active:scale-95"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                                <button
                                    type="button"
                                    @click="goNext()"
                                    aria-label="Next teacher"
                                    class="absolute -right-3 top-8 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-700 shadow-md ring-1 ring-gray-200 transition-all duration-200 ease-out hover:scale-110 hover:text-primary-600 hover:shadow-lg active:scale-95"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($homeBlocks->has('principal'))
                    <div
                        x-data="{ shown: false }"
                        x-init="
                            const observer = new IntersectionObserver((entries) => {
                                entries.forEach((entry) => { if (entry.isIntersecting) { shown = true; observer.disconnect(); } });
                            }, { threshold: 0.15 });
                            observer.observe($el);
                        "
                        :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-10'"
                        style="transition-delay: 150ms"
                        class="flex flex-col gap-4 overflow-hidden rounded-[16px] bg-primary-50 p-5 transition-all duration-[900ms] ease-out sm:flex-row sm:items-stretch lg:col-span-4"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-xl font-serif leading-none text-primary-300">&ldquo;</p>
                            <p class="mt-1 text-sm font-bold text-primary-700">From the Principal's Desk</p>
                            <div class="relative mt-1.5">
                                <x-website-blocks :blocks="$homeBlocks->get('principal')" height="90px" />
                            </div>
                            @if ($website->principal_name)
                                <p class="mt-2 text-xs font-bold text-gray-900">{{ $website->principal_name }}</p>
                                <p class="text-[11px] font-medium text-gray-600">{{ $website->principal_title ?: 'Principal' }}</p>
                            @endif
                        </div>
                        @if ($website->principalPhotoUrl())
                            <div class="h-32 shrink-0 overflow-hidden rounded-[12px] sm:h-auto sm:w-48">
                                <img src="{{ $website->principalPhotoUrl() }}" class="h-full w-full object-cover" alt="{{ $website->principal_name }}">
                            </div>
                        @endif
                    </div>
                @endif

                @if ($homeBlocks->has('quote'))
                    <div
                        x-data="{ shown: false }"
                        x-init="
                            const observer = new IntersectionObserver((entries) => {
                                entries.forEach((entry) => { if (entry.isIntersecting) { shown = true; observer.disconnect(); } });
                            }, { threshold: 0.15 });
                            observer.observe($el);
                        "
                        :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-10'"
                        style="transition-delay: 300ms"
                        class="relative flex flex-col justify-between overflow-hidden rounded-[16px] bg-amber-50 p-5 transition-all duration-[900ms] ease-out lg:col-span-2"
                    >
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-amber-600">Quote of the Week</p>
                            <div class="relative mt-1.5">
                                <x-website-blocks :blocks="$homeBlocks->get('quote')" height="90px" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                @if ($website->quote_author)
                                    <p class="truncate text-xs font-bold text-gray-900">&mdash; {{ $website->quote_author }}</p>
                                    @if ($website->quote_author_role)
                                        <p class="truncate text-[10px] text-gray-500">{{ $website->quote_author_role }}</p>
                                    @endif
                                @endif
                            </div>
                            <svg class="h-5 w-5 shrink-0 text-amber-300" viewBox="0 0 24 24" fill="currentColor"><path d="M9 7c-2.8 0-5 2.2-5 5v5h5v-5H6.5C6.5 9.8 7.6 8.5 9 8.5V7zm9 0c-2.8 0-5 2.2-5 5v5h5v-5h-2.5c0-2.2 1.1-3.5 2.5-3.5V7z" /></svg>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- What people say --}}
    @if ($testimonials->isNotEmpty())
        <section class="border-t border-gray-100 bg-gray-50 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <div class="text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Testimonials</p>
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
                            <a href="{{ route('public.school-careers.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View All Jobs</a>
                        </div>
                        <div class="mt-4 overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm">
                            @foreach ($openJobs as $job)
                                <a href="{{ route('public.school-careers.index', $school) }}" class="flex items-center justify-between gap-3 border-b border-gray-100 p-4 transition-colors duration-150 last:border-b-0 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $job->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $job->employment_type->label() }}@if ($job->location) &middot; {{ $job->location }} @endif</p>
                                    </div>
                                    <span class="shrink-0 rounded-[8px] border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-600">View Job</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- About --}}
    @php
        $aboutBody = $school->websiteBlocksFor('about')->firstWhere('section', 'body');
    @endphp
    @if ($aboutBody && $aboutBody['content'])
        <section id="about" class="scroll-mt-20 border-t border-gray-100 bg-gray-50 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">About Us</p>
                <p class="mt-4 whitespace-pre-line text-base leading-relaxed text-gray-700">{{ $aboutBody['content'] }}</p>
            </div>
        </section>
    @endif

    {{-- Facilities preview --}}
    @if ($facilities->isNotEmpty())
        <section class="px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Facilities</p>
                        <h2 class="mt-1 text-xl font-extrabold text-gray-900">Built for Learning & Growth</h2>
                    </div>
                    <a href="{{ route('public.school-facilities.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View All Facilities</a>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($facilities as $facility)
                        <a href="{{ route('public.school-facilities.index', $school) }}" class="group overflow-hidden rounded-[8px] border border-gray-200">
                            <div class="aspect-square overflow-hidden bg-gray-100">
                                @if ($facility->imageUrl())
                                    <img src="{{ $facility->imageUrl() }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-300">
                                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20V10.5L12 4l8 6.5V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </div>
                                @endif
                            </div>
                            <p class="truncate px-2 py-1.5 text-center text-[11px] font-semibold text-gray-700">{{ $facility->name }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- News, Events, Gallery grid --}}
    @if ($latestNews->isNotEmpty() || $upcomingEvents->isNotEmpty() || $galleryImages->isNotEmpty())
        <section class="border-t border-gray-100 bg-gray-50 px-4 py-16 sm:px-6">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 lg:grid-cols-3">
                @if ($latestNews->isNotEmpty())
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Latest News</h2>
                            <a href="{{ route('public.school-news.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View All</a>
                        </div>
                        <div class="mt-4 space-y-4">
                            @foreach ($latestNews as $post)
                                <a href="{{ route('public.school-news.show', [$school, $post]) }}" class="flex gap-3 rounded-[10px] bg-white p-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                    <div class="h-14 w-16 shrink-0 overflow-hidden rounded-[8px] bg-gray-100">
                                        @if ($post->imageUrl())
                                            <img src="{{ $post->imageUrl() }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-gray-300">
                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" /></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        @if ($post->category)
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-primary-600">{{ $post->category }}</span>
                                        @endif
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $post->title }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $post->published_at->format('M j, Y') }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($upcomingEvents->isNotEmpty())
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Upcoming Events</h2>
                            <a href="{{ route('public.school-events.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View All</a>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach ($upcomingEvents->take(4) as $event)
                                <div class="flex items-start gap-3 rounded-[10px] bg-white p-3 shadow-sm">
                                    <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-[8px] bg-primary-50 text-primary-700">
                                        <span class="text-[9px] font-bold uppercase leading-none">{{ $event->starts_at->format('M') }}</span>
                                        <span class="text-sm font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $event->title }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $event->starts_at->format('M j, Y') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($galleryImages->isNotEmpty())
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Gallery Highlights</h2>
                            <a href="{{ route('public.school-gallery.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View Full Gallery</a>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            @foreach ($galleryImages->take(6) as $image)
                                <div class="aspect-square overflow-hidden rounded-[8px]">
                                    <img src="{{ $image->imageUrl() }}" alt="{{ $image->caption }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-110">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif
</x-public-site-layout>
