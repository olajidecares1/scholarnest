@php
    $customDomain = $domains->firstWhere('is_primary', true);
    $customDomainLive = $customDomain && $customDomain->isVerifiedAndActive();
    $publicUrl = $school->publicUrl('public.school-website');
    $aboutUrl = $school->publicUrl('public.school-about.index');
    $admissionsUrl = $school->publicUrl('public.school-admissions.index');
    $contactUrl = $school->publicUrl('public.school-contact.index');
@endphp

<x-dashboard-layout page-title="Website" page-subtitle="Build your school's public website.">
    <div
        class="space-y-6"
        x-data="{
            tab: localStorage.getItem('websiteEditTab') ?? 'home',
            galleryOpen: false,
            navLinkOpen: false,
            navLinkEditing: null,
            preview: false,
        }"
        x-effect="localStorage.setItem('websiteEditTab', tab)"
        :class="{ 'edn-preview-mode': preview }"
    >
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Publication Status</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $website->is_published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $website->is_published ? 'Live' : 'Unpublished' }}
                        </span>
                    </div>
                    @if ($website->is_published)
                        <a href="{{ $publicUrl }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700">{{ $publicUrl }}</a>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Publish to make your website visible at {{ $publicUrl }}</p>
                    @endif
                    <button type="button" @click="tab = 'domain'" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                        {{ $customDomainLive ? 'Serving on your custom domain' : 'Connect a custom domain' }}
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('website.publish') }}">
                    @csrf
                    <button type="submit" class="rounded-[8px] {{ $website->is_published ? 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700' : 'bg-blue-600 text-white hover:bg-blue-700' }} px-4 py-2 text-sm font-semibold transition-all duration-200">
                        {{ $website->is_published ? 'Unpublish' : 'Publish Website' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Toolbar: theme color + preview toggle --}}
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-[8px] border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800"
            x-data="{
                colorInput: '{{ $website->brand_primary_color ?? '' }}',
                normalize() {
                    const hex = window.normalizeCssColorToHex(this.colorInput);
                    if (hex) { this.colorInput = hex; }
                },
            }"
        >
            <form method="POST" action="{{ route('website.update-brand-color') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                @method('PUT')
                <label class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Theme Color</label>
                <input type="text" name="brand_primary_color" x-model="colorInput" @change="normalize()" placeholder="e.g. Indigo, Dark Blue, #1877f2" class="h-9 w-52 rounded-[6px] border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <input type="color" :value="colorInput || '#166fe5'" @input="colorInput = $event.target.value" class="h-9 w-10 rounded border border-gray-300 dark:border-gray-600">
                <button type="submit" class="rounded-[6px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Apply Theme Color</button>
            </form>

            <button
                type="button"
                @click="preview = ! preview"
                class="flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                x-text="preview ? 'Back to Editing' : 'Preview'"
            ></button>
        </div>

        {{-- Page tabs --}}
        <div class="flex flex-wrap gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800" x-show="! preview">
            @foreach ([
                ['key' => 'home', 'label' => 'Home Page'],
                ['key' => 'about', 'label' => 'About Us'],
                ['key' => 'admissions', 'label' => 'Admissions'],
                ['key' => 'contact', 'label' => 'Contact Us'],
                ['key' => 'footer', 'label' => 'Footer'],
                ['key' => 'navigation', 'label' => 'Navigation'],
                ['key' => 'domain', 'label' => 'Custom Domain'],
            ] as $section)
                <button
                    type="button"
                    @click="tab = '{{ $section['key'] }}'"
                    :class="tab === '{{ $section['key'] }}' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'"
                    class="rounded-[6px] px-4 py-1.5 text-sm font-semibold transition-all duration-200 ease-out"
                >
                    {{ $section['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Home Page --}}
        <div x-show="tab === 'home'" x-transition.opacity.duration.200ms style="display: none;" class="space-y-6">
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">Home Page</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">Click any element in the preview to select it, drag to reposition, resize from its handles, and double-click text to type directly.</p>
                </div>

                <div class="space-y-6 bg-white p-6 dark:bg-gray-800">
                    <form
                        method="POST"
                        action="{{ route('website.blocks.update', 'home') }}"
                        x-data="pageBuilder(@js($school->websiteBlocksFor('home')), @json(config('website_fonts.fonts')))"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="blocks" x-bind:value="allBlocksJson()">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                            <div class="space-y-6">
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'hero', 'label' => 'Hero Banner', 'height' => '480px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'feature-icons', 'label' => 'Feature Highlights', 'height' => '220px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'stats', 'label' => 'Stats Banner', 'height' => '110px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'slogan', 'label' => 'Slogan Banner', 'height' => '80px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'principal', 'label' => "Principal's Desk", 'height' => '100px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'quote', 'label' => 'Quote of the Week', 'height' => '100px'])
                            </div>
                            <div x-show="! preview">
                                @include('school-admin.website._inspector')
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end" x-show="! preview">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Adjustments</button>
                        </div>
                    </form>

                    <hr class="border-gray-100 dark:border-gray-700" x-show="! preview">

                    <form method="POST" action="{{ route('website.update-header-hero-fields') }}" x-show="! preview" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Top Utility Bar</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="topbar_announcement" label="Welcome Message" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" value="{{ $website->topbar_announcement }}" placeholder="Welcome to {{ $school->name }}" helper="Optional. Left side of the top bar." />
                            <x-text-field name="topbar_badge_text" label="Badge Text" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->topbar_badge_text }}" placeholder="Admissions Open {{ $school->current_session ?? now()->year }}" helper="Optional." />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="topbar_link_text" label="Portal Link Text" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" value="{{ $website->topbar_link_text }}" placeholder="School Portal" helper="Optional." />
                            <x-text-field name="topbar_link_url" label="Portal Link URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->topbar_link_url }}" placeholder="{{ route('login') }}" helper="Optional." />
                        </div>

                        <div>
                            <input type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fallback hero background image, only used if you haven't added any slides to the Hero Slider below.</p>
                            @if ($website->heroImageUrl())
                                <img src="{{ $website->heroImageUrl() }}" class="mt-2 h-24 w-full max-w-sm rounded-[8px] object-cover">
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="whats_happening_title" label="\"What's Happening\" Card Title" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" value="{{ $website->whats_happening_title }}" placeholder="What's Happening" helper="Optional." />
                            <label class="flex items-center gap-2 self-center text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="show_whats_happening" value="1" {{ $website->show_whats_happening ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Show this card on the hero (lists your upcoming events)
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Header Settings</button>
                        </div>
                    </form>

                    <hr class="border-gray-100 dark:border-gray-700">

                    {{-- Hero Slider --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hero Slider</h3>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Multiple background images that rotate in the hero banner.</p>
                            </div>
                            <form method="POST" action="{{ route('website.hero-slides.store') }}" enctype="multipart/form-data">
                                @csrf
                                <label class="flex cursor-pointer items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                    Add Slide
                                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="hidden" onchange="this.form.submit()">
                                </label>
                            </form>
                        </div>

                        <div class="mt-4">
                            @if ($heroSlides->isEmpty())
                                <p class="rounded-[8px] border border-dashed border-gray-300 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No hero slides yet. Click "Add Slide" to upload the first one — until then the fallback hero image above is used.</p>
                            @else
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    @foreach ($heroSlides as $index => $slide)
                                        <div class="group relative overflow-hidden rounded-[8px] border border-gray-200 dark:border-gray-700">
                                            <img src="{{ $slide->imageUrl() }}" class="h-32 w-full object-cover">
                                            <span class="absolute left-1.5 top-1.5 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-bold text-white">{{ $index + 1 }}</span>

                                            <div class="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition-opacity duration-150 group-hover:opacity-100">
                                                @if (! $loop->first)
                                                    <form method="POST" action="{{ route('website.hero-slides.move', $slide) }}">
                                                        @csrf
                                                        <input type="hidden" name="direction" value="up">
                                                        <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm hover:bg-white" title="Move earlier">
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 15l6-6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                                @if (! $loop->last)
                                                    <form method="POST" action="{{ route('website.hero-slides.move', $slide) }}">
                                                        @csrf
                                                        <input type="hidden" name="direction" value="down">
                                                        <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm hover:bg-white" title="Move later">
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('website.hero-slides.destroy', $slide) }}" onsubmit="return confirm('Remove this slide?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-red-600 shadow-sm hover:bg-white" title="Delete">
                                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- About Us --}}
        <div x-show="tab === 'about'" x-transition.opacity.duration.200ms style="display: none;">
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">About Us Page</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">{{ $aboutUrl }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-gray-800">
                    <form
                        method="POST"
                        action="{{ route('website.blocks.update', 'about') }}"
                        x-data="pageBuilder(@js($school->websiteBlocksFor('about')), @json(config('website_fonts.fonts')))"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="blocks" x-bind:value="allBlocksJson()">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                            <div class="space-y-6">
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'intro', 'label' => 'Intro Subtitle', 'height' => '90px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'body', 'label' => 'About Text', 'height' => '220px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'slogan', 'label' => 'Slogan Callout', 'height' => '90px'])
                            </div>
                            <div x-show="! preview">
                                @include('school-admin.website._inspector')
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end" x-show="! preview">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Adjustments</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Admissions --}}
        <div x-show="tab === 'admissions'" x-transition.opacity.duration.200ms style="display: none;">
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">Admissions Page</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">{{ $admissionsUrl }}</p>
                </div>
                <div class="bg-white p-6 dark:bg-gray-800">
                    <form
                        method="POST"
                        action="{{ route('website.blocks.update', 'admissions') }}"
                        x-data="pageBuilder(@js($school->websiteBlocksFor('admissions')), @json(config('website_fonts.fonts')))"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="blocks" x-bind:value="allBlocksJson()">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                            <div class="space-y-6">
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'intro', 'label' => 'Intro', 'height' => '100px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'steps', 'label' => 'Application Steps (cards)', 'height' => '280px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'requirements', 'label' => 'Requirements List', 'height' => '260px'])
                            </div>
                            <div x-show="! preview">
                                @include('school-admin.website._inspector')
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end" x-show="! preview">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Adjustments</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Contact Us --}}
        <div x-show="tab === 'contact'" x-transition.opacity.duration.200ms style="display: none;" class="space-y-6">
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">Contact Us Page</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">{{ $contactUrl }}</p>
                </div>
                <div class="space-y-6 bg-white p-6 dark:bg-gray-800">
                    <form
                        method="POST"
                        action="{{ route('website.blocks.update', 'contact') }}"
                        x-data="pageBuilder(@js($school->websiteBlocksFor('contact')), @json(config('website_fonts.fonts')))"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="blocks" x-bind:value="allBlocksJson()">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                            <div class="space-y-6">
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'cards', 'label' => 'Contact Info Cards', 'height' => '220px'])
                            </div>
                            <div x-show="! preview">
                                @include('school-admin.website._inspector')
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end" x-show="! preview">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Adjustments</button>
                        </div>
                    </form>

                    <hr class="border-gray-100 dark:border-gray-700">

                    <form method="POST" action="{{ route('website.update-contact-fields') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact Details</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Powers the tel:/mailto: links and social icons across your site.</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-text-field name="contact_email" label="Contact Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" value="{{ $website->contact_email }}" helper="Optional." />
                            <x-text-field name="contact_phone" label="Contact Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" value="{{ $website->contact_phone }}" helper="Optional." />
                            <x-text-field name="contact_address" label="Address" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" value="{{ $website->contact_address }}" helper="Optional." />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-text-field name="facebook_url" label="Facebook URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->facebook_url }}" helper="Optional." />
                            <x-text-field name="twitter_url" label="Twitter / X URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->twitter_url }}" helper="Optional." />
                            <x-text-field name="instagram_url" label="Instagram URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ $website->instagram_url }}" helper="Optional." />
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Contact Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div x-show="tab === 'footer'" x-transition.opacity.duration.200ms style="display: none;">
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">Footer</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">Shown at the bottom of every page. Quick Links, Academics, and the newsletter box come automatically and aren't edited here.</p>
                </div>
                <div class="bg-white p-6 dark:bg-gray-800">
                    <form
                        method="POST"
                        action="{{ route('website.blocks.update', 'footer') }}"
                        x-data="pageBuilder(@js($school->websiteBlocksFor('footer')), @json(config('website_fonts.fonts')))"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="blocks" x-bind:value="allBlocksJson()">

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
                            <div class="space-y-6">
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'description', 'label' => 'School Description', 'height' => '80px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'contact', 'label' => 'Contact Lines', 'height' => '100px'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'cta', 'label' => 'Call-to-Action Banner', 'height' => '100px'])
                            </div>
                            <div x-show="! preview">
                                @include('school-admin.website._inspector')
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end" x-show="! preview">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Adjustments</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <div x-show="tab === 'navigation'" x-transition.opacity.duration.200ms style="display: none;">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Navigation Menu</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Links shown in the navbar. Leave empty to use the default menu.</p>
                    </div>
                    <button
                        type="button"
                        @click="navLinkEditing = null; navLinkOpen = true"
                        class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                        Add Link
                    </button>
                </div>

                <div class="mt-4">
                    @if ($navLinks->isEmpty())
                        <p class="rounded-[8px] border border-dashed border-gray-300 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No custom links yet — the default menu (Home, About, Academics, Admissions, News, Events, Facilities, Gallery, Contact) is being used.</p>
                    @else
                        <div class="divide-y divide-gray-100 rounded-[8px] border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            @foreach ($navLinks as $index => $navLink)
                                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $navLink->label }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $navLink->url }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        @if (! $loop->first)
                                            <form method="POST" action="{{ route('website.nav-links.move', $navLink) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-[6px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Move earlier">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </button>
                                            </form>
                                        @endif
                                        @if (! $loop->last)
                                            <form method="POST" action="{{ route('website.nav-links.move', $navLink) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-[6px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" title="Move later">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </button>
                                            </form>
                                        @endif
                                        <button
                                            type="button"
                                            @click="navLinkEditing = @js(['uuid' => $navLink->uuid, 'label' => $navLink->label, 'url' => $navLink->url]); navLinkOpen = true"
                                            class="rounded-[6px] px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('website.nav-links.destroy', $navLink) }}" onsubmit="return confirm('Remove this link?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[6px] px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Add/Edit Nav Link modal --}}
            <div x-show="navLinkOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="navLinkOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="navLinkEditing ? 'Edit Link' : 'Add Link'"></h3>
                    <form
                        method="POST"
                        :action="navLinkEditing ? '{{ route('website.nav-links.update', ['navLink' => '__ID__']) }}'.replace('__ID__', navLinkEditing.uuid) : '{{ route('website.nav-links.store') }}'"
                        class="mt-4 space-y-4"
                    >
                        @csrf
                        <template x-if="navLinkEditing"><input type="hidden" name="_method" value="PUT"></template>

                        <x-text-field name="label" label="Label" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="navLinkEditing ? navLinkEditing.label : ''" placeholder="e.g. Alumni" required />
                        <x-text-field name="url" label="URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="navLinkEditing ? navLinkEditing.url : ''" placeholder="e.g. /schools/your-school/gallery or #contact" required />

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="navLinkOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Link</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Custom Domain --}}
        <div x-show="tab === 'domain'" x-transition.opacity.duration.200ms style="display: none;">
            @include('school-admin.custom-domain._wizard', ['domains' => $domains, 'hasCustomDomainAccess' => $hasCustomDomainAccess])
        </div>

        {{-- Gallery --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]" x-show="! preview">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Gallery</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Photos shown on your public website.</p>
                </div>
                <button
                    type="button"
                    @click="galleryOpen = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Image
                </button>
            </div>

            <div class="p-6">
                @if ($galleryImages->isEmpty())
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No gallery images yet. Click "Add Image" to upload one.</p>
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($galleryImages as $image)
                            <div class="group relative overflow-hidden rounded-[8px] border border-gray-200 dark:border-gray-700">
                                <img src="{{ $image->imageUrl() }}" class="h-32 w-full object-cover">
                                @if ($image->caption)
                                    <p class="truncate bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">{{ $image->caption }}</p>
                                @endif
                                <form method="POST" action="{{ route('website.gallery.destroy', $image) }}" onsubmit="return confirm('Remove this image?');" class="absolute right-1.5 top-1.5">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-red-600 opacity-0 shadow-sm transition-opacity duration-150 group-hover:opacity-100">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Add Gallery Image modal --}}
        <div x-show="galleryOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="galleryOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Gallery Image</h3>
                <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                    </div>
                    <x-text-field name="caption" label="Caption" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Optional." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="galleryOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
