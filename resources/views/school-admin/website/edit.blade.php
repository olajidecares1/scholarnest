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
                        <p class="field-hint mt-1">Publish to make your website visible at {{ $publicUrl }}</p>
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
                <div>
                    <label class="field-label" for="brand_primary_color">Company Color</label>
                    <small class="field-hint mt-0.5 block max-w-md">Choose the primary color used throughout your school's public website for buttons, headings, icons, borders, links and other accents.</small>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <input id="brand_primary_color" type="text" name="brand_primary_color" x-model="colorInput" @change="normalize()" placeholder="e.g. Indigo, Dark Blue, #1877f2" class="w-52">
                        <input type="color" :value="colorInput || '#166fe5'" @input="colorInput = $event.target.value" class="w-10" aria-label="Pick a color">
                        <button type="submit" class="rounded-[6px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Apply Theme Color</button>
                    </div>
                </div>
            </form>

            <button
                type="button"
                @click="preview = ! preview"
                class="flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                x-text="preview ? 'Back to Editing' : 'Preview'"
            ></button>
        </div>

        {{-- Typography.

             The weights offered are the ones the CHOSEN FAMILY actually
             publishes, not a fixed 300-800 range, Google returns a stylesheet
             without a weight a family does not ship, and the browser then fakes
             it, which looks worse than the weight the school asked for. The
             slider is therefore indexed over that family's real list, and
             switching family re-points it at the new one.

             The preview is live and local: it sets the font on a sample of
             real page text as the controls move, so nobody has to save and
             reload to find out what they picked. --}}
        @php
            $fontFamilies = \App\Support\WebsiteTypography::families();
            $currentFamily = \App\Support\WebsiteTypography::familyFor($website);
            $currentWeight = \App\Support\WebsiteTypography::weightFor($website);
        @endphp

        <div
            class="rounded-[8px] border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            x-show="! preview"
            x-data="{
                families: @js($fontFamilies),

                // The real stacks, so the preview shows what the page will
                // actually render, a system face previewed with the generic
                // sans fallback would promise Helvetica where the site falls
                // back to a serif or a display face.
                stacks: @js(collect(\App\Support\WebsiteTypography::systemFamilies())->map(fn ($f) => $f['stack'])->all()),
                family: @js($currentFamily),
                weight: {{ $currentWeight }},

                get stack() {
                    return this.stacks[this.family]
                        ?? `'${this.family}', ui-sans-serif, system-ui, sans-serif`;
                },

                get weights() {
                    return this.families[this.family] ?? [400];
                },

                // The slider moves over POSITIONS in the family's list, not
                // over the numbers themselves. The published weights are not
                // evenly spaced, Lato ships 400, 700, 900, so a numeric
                // range would let it rest on values that do not exist.
                get index() {
                    const at = this.weights.indexOf(this.weight);

                    return at === -1 ? 0 : at;
                },

                pick(position) {
                    this.weight = this.weights[Number(position)] ?? this.weights[0];
                },

                onFamilyChange() {
                    // Keep the nearest real weight rather than snapping to the
                    // lightest: someone on Montserrat 700 who tries Lato meant
                    // bold, not regular.
                    const wanted = this.weight;

                    this.weight = this.weights.reduce(
                        (best, w) => (Math.abs(w - wanted) < Math.abs(best - wanted) ? w : best),
                        this.weights[0],
                    );
                },
            }"
        >
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Typography</h3>
            <small class="field-hint mt-0.5 block">Controls the text style of your public website. Headings keep their own stronger weights so your page keeps its shape.</small>

            <form method="POST" action="{{ route('website.update-typography') }}" class="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.1fr)]">
                @csrf
                @method('PUT')

                <div>
                    <label class="field-label" for="font_family">Font Family</label>
                    <small class="field-hint mt-0.5 block">Select the font style you want your school's public website to use for its text and content.</small>
                    {{-- Grouped, because the difference is real and a school
                         should be able to see it before choosing. The web
                         fonts are delivered with the page and look the same on
                         every device. The installed ones are only used if the
                         visitor's own computer has them, Algerian is on
                         Windows machines with Office and almost nowhere else,
                         so they fall back for everyone who does not. --}}
                    <select id="font_family" name="font_family" x-model="family" @change="onFamilyChange()" class="mt-1.5 w-full">
                        <optgroup label="Web fonts (look the same for every visitor)">
                            @foreach (array_keys(config('website_fonts.fonts', [])) as $family)
                                <option value="{{ $family }}">{{ $family }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Installed fonts (only for visitors who have them)">
                            @foreach (array_keys(\App\Support\WebsiteTypography::systemFamilies()) as $family)
                                <option value="{{ $family }}">{{ $family }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    @error('font_family')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="field-label" for="font_weight_range">Font Weight</label>
                    <small class="field-hint mt-0.5 block">Use the slider to make the general website text lighter or bolder. This is the base weight; headings stay stronger.</small>

                    <div class="mt-2 flex items-center gap-3">
                        <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Light</span>
                        <input
                            id="font_weight_range"
                            type="range"
                            min="0"
                            :max="weights.length - 1"
                            step="1"
                            :value="index"
                            @input="pick($event.target.value)"
                            class="edn-brand-range h-2 min-w-0 flex-1"
                            aria-label="Font weight"
                        >
                        <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Bold</span>
                    </div>

                    {{-- The number itself, so the slider is not a mystery. --}}
                    <p class="mt-1.5 text-xs font-semibold text-gray-900 dark:text-white">
                        Font Weight: <span x-text="weight"></span>
                    </p>

                    <input type="hidden" name="font_weight" :value="weight">
                    @error('font_weight')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="field-label">Live Preview</label>
                    <small class="field-hint mt-0.5 block">How your chosen font and weight will look on your website.</small>

                    <div
                        class="mt-1.5 rounded-[8px] border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/40"
                        :style="`font-family: ${stack}`"
                    >
                        {{-- The heading stays bold whatever the slider says,
                             because that is what the website does, previewing
                             it at the base weight would promise a page the
                             school will not get. --}}
                        <p class="text-[15px] font-bold text-gray-900 dark:text-white">{{ $school->name }}</p>
                        <p class="text-[13px] text-gray-900 dark:text-gray-100" :style="`font-weight: ${weight}`">Welcome to Our School</p>
                        <p class="mt-0.5 text-[11.5px] text-gray-600 dark:text-gray-400" :style="`font-weight: ${weight}`">Learn more about our school.</p>
                    </div>

                    <button type="submit" class="mt-3 w-full rounded-[6px] bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save Typography</button>
                </div>
            </form>
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

                    <form method="POST" action="{{ route('website.update-header-hero-fields') }}" x-show="! preview" enctype="multipart/form-data" class="space-y-2">
                        @csrf
                        @method('PUT')

                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Top Utility Bar</h3>
                        <small class="field-hint">The thin strip above the main menu, on every page of your website. Leave all four blank and the strip is hidden.</small>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="topbar_announcement" label="Welcome Message" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" :value="$website->topbar_announcement" placeholder="Welcome to {{ $school->name }}" helper="Optional. A short greeting on the left of the strip. Keep it to a few words, as it shares one line with everything else." />
                            <x-text-field name="topbar_badge_text" label="Badge Text" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="$website->topbar_badge_text" placeholder="Admissions Open {{ $school->current_session ?? now()->year }}" helper="Optional. A small highlighted pill for a short announcement, such as an open admissions window." />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="topbar_link_text" label="Portal Link Text" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" :value="$website->topbar_link_text" placeholder="School Portal" helper="Optional. The wording of the link on the right of the strip, where parents and staff sign in." />
                            <x-text-field name="topbar_link_url" label="Portal Link URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="$website->topbar_link_url" placeholder="{{ route('login') }}" helper="Optional. Where that link goes. Leave blank to use your AkademicNest login page." />
                        </div>

                        <div>
                            <label class="field-label" for="hero_image">Fallback Hero Image</label>
                            <input id="hero_image" type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp" class="mt-1.5 w-full">
                            <small class="field-hint mt-1">The single large photograph behind the top of your home page, used only when the Hero Slider below is empty. A wide landscape shot of your building, grounds or pupils works best. It is stretched across the full width of the screen, so a small image will look soft. JPEG, PNG or WebP.</small>
                            @if ($website->heroImageUrl())
                                <img src="{{ $website->heroImageUrl() }}" class="mt-2 h-24 w-full max-w-sm rounded-[8px] object-cover">
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-text-field name="whats_happening_title" label="\"What's Happening\" Card Title" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" :value="$website->whats_happening_title" placeholder="What's Happening" helper="Optional. The heading on the small card of upcoming events that sits over the hero image. Leave blank to use &quot;What's Happening&quot;." />
                            <div class="self-center">
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                    <input type="checkbox" name="show_whats_happening" value="1" {{ $website->show_whats_happening ? 'checked' : '' }} class="w-4 text-blue-600">
                                    Show this card on the hero
                                </label>
                                <small class="field-hint mt-1">Ticked, the card lists your next few events from the Events page automatically. Unticked, the hero image is shown on its own.</small>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Header Settings</button>
                        </div>
                    </form>

                    <hr class="border-gray-100 dark:border-gray-700">

                    {{-- Hero Slider.

                         Given its own titled panel rather than the small grey
                         label it used to carry. It sat below a long form under
                         a heading the same size as a field hint, and was
                         reported as missing more than once, the pictures at
                         the top of a school's home page are one of the first
                         things anybody wants to change. --}}
                    <div id="hero-slider" class="scroll-mt-24 overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                        <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                            <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">Hero Slider</h2>
                            <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">The photographs at the very top of your home page.</p>
                        </div>

                        <div class="bg-white p-6 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <small class="field-hint mt-0.5 max-w-md block">Several photographs that fade from one to the next behind the top of your home page. Add two or more and they replace the fallback image above. Use the arrows on each to set the order they appear in.</small>
                            </div>
                            <form method="POST" action="{{ route('website.hero-slides.store') }}" enctype="multipart/form-data">
                                @csrf
                                <label class="flex cursor-pointer items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                    Add Slide
                                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="hidden" onchange="this.form.requestSubmit()">
                                </label>
                            </form>
                        </div>

                        <div class="mt-4">
                            @if ($heroSlides->isEmpty())
                                <p class="rounded-[8px] border border-dashed border-gray-300 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No hero slides yet. Click "Add Slide" to upload the first one. Until then the fallback hero image above is used.</p>
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
        </div>

        {{-- About Us --}}
        <div x-show="tab === 'about'" x-transition.opacity.duration.200ms style="display: none;" class="space-y-6">
            {{-- The picture behind the whole About band. Distinct from the
                 photograph in the frame beside the prose, which is set with
                 the rest of the About fields. --}}
            <x-card-background-field
                :action="route('website.about-card-background')"
                :current="$website->aboutCardImageUrl()"
                title="About section background"
                description="Sits behind the About section of your public website. Leave it empty for the plain background."
            />

            {{-- Our Mission, Our Vision and Our Values.

                 These were already editable, and had been all along, but the
                 form lived inside the CONTACT US tab, which is not anywhere a
                 person looks for the About section. It is here now, under the
                 tab named after the page it edits. --}}
            <div class="overflow-hidden rounded-[10px] border-2 border-blue-100 dark:border-blue-900/40">
                <div class="border-b border-blue-100 bg-blue-50/60 px-6 py-4 dark:border-blue-900/40 dark:bg-blue-900/10">
                    <h2 class="text-sm font-extrabold text-blue-700 dark:text-blue-400">About Section</h2>
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">Your headline, your introduction, and the three statements shown beneath them.</p>
                </div>

                <div class="bg-white p-6 dark:bg-gray-800">
                    <form method="POST" action="{{ route('website.update-about-fields') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <x-text-field
                            name="about_headline"
                            label="Headline"
                            icon="M4 6h16M4 12h10M4 18h7"
                            :value="old('about_headline', $website->about_headline)"
                            placeholder="e.g. Building strong minds, character and future leaders."
                            helper="One line, shown in bold above the paragraph. This is usually the first sentence a visiting parent reads, so say what makes your school worth choosing."
                        />

                        <div>
                            <label class="field-label" for="about_text">About your school</label>
                            <textarea id="about_text" name="about_text" rows="3" class="mt-1.5 w-full" placeholder="A short paragraph about what your school offers and stands for.">{{ old('about_text', $website->about_text) }}</textarea>
                            <small class="field-hint mt-1">A short introduction shown under the headline on your home page. Two or three sentences is plenty. Longer writing belongs on the About Us page tab.</small>
                            @error('about_text')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        {{-- Three separate boxes because they are three
                             different questions, and the page lays them out
                             side by side beneath the introduction. --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            @foreach ([
                                ['mission', 'Our Mission', 'To provide quality education that empowers students to excel and impact their world positively.', 'What your school sets out to do for its pupils, day to day.'],
                                ['vision', 'Our Vision', 'To be a leading institution recognised for academic excellence and character development.', 'What your school is working towards becoming: the longer view.'],
                                ['values', 'Our Values', 'Excellence, Integrity, Discipline, Leadership, Innovation and Respect.', 'The principles your school holds its pupils and staff to. A short list reads best here.'],
                            ] as [$field, $label, $example, $description])
                                <div>
                                    <label class="field-label" for="about_{{ $field }}">{{ $label }}</label>
                                    <textarea id="about_{{ $field }}" name="{{ $field }}" rows="3" class="mt-1.5 w-full" placeholder="{{ $example }}">{{ old($field, $website->$field) }}</textarea>
                                    <small class="field-hint mt-1">{{ $description }} Shown in one of three cards beneath your introduction.</small>
                                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>

                        {{-- The Principal's Desk.

                             These columns existed all along; the section that
                             showed them was removed from the public site and
                             the fields went with it, so a school had a
                             principal's message stored and no way to reach it.
                             Both are back, and the section now sits below
                             Events, Facilities and the Gallery. --}}
                        <div class="rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                            <h3 class="text-[13px] font-extrabold text-gray-900 dark:text-white">Principal&rsquo;s Desk</h3>
                            <small class="field-hint mt-0.5 block">A short message from your Principal, shown on the home page below your Gallery. Leave it all blank and the section does not appear.</small>

                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" for="principal_name">Principal&rsquo;s name</label>
                                    <input id="principal_name" type="text" name="principal_name" maxlength="150" class="mt-1.5 w-full" placeholder="Mr. Gregory A. Eze" value="{{ old('principal_name', $website->principal_name) }}">
                                    <small class="field-hint mt-1">Printed beneath the message.</small>
                                    @error('principal_name')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="field-label" for="principal_title">Title</label>
                                    <input id="principal_title" type="text" name="principal_title" maxlength="120" class="mt-1.5 w-full" placeholder="Principal" value="{{ old('principal_title', $website->principal_title) }}">
                                    <small class="field-hint mt-1">Optional. Defaults to &ldquo;Principal&rdquo;.</small>
                                    @error('principal_title')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="field-label" for="principal_message">Message</label>
                                <textarea id="principal_message" name="principal_message" rows="4" maxlength="2000" class="mt-1.5 w-full" placeholder="A word of welcome to families visiting your website.">{{ old('principal_message', $website->principal_message) }}</textarea>
                                <small class="field-hint mt-1">A few sentences reads best. Line breaks are kept.</small>
                                @error('principal_message')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                                @if ($website->principalPhotoUrl())
                                    <img src="{{ $website->principalPhotoUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-[8px] border border-gray-200 object-cover dark:border-gray-700">
                                @else
                                    <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-center text-[10px] font-semibold text-gray-500 dark:border-gray-600 dark:text-gray-400">No photo</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <label class="field-label" for="principal_photo">Principal&rsquo;s photograph</label>
                                    <input id="principal_photo" type="file" name="principal_photo" accept=".jpg,.jpeg,.png,.webp" class="mt-1.5 w-full text-sm">
                                    <small class="field-hint mt-1">Optional. Shown beside the message. Leave blank to keep the current one. JPEG, PNG or WebP.</small>
                                    @error('principal_photo')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Quote of the Week. --}}
                        <div class="rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                            <h3 class="text-[13px] font-extrabold text-gray-900 dark:text-white">Quote of the Week</h3>
                            <small class="field-hint mt-0.5 block">An optional short quotation, shown beside the Principal&rsquo;s message.</small>

                            <div class="mt-3">
                                <label class="field-label" for="quote_text">Quotation</label>
                                <textarea id="quote_text" name="quote_text" rows="2" maxlength="500" class="mt-1.5 w-full" placeholder="Education is the most powerful weapon which you can use to change the world.">{{ old('quote_text', $website->quote_text) }}</textarea>
                                @error('quote_text')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" for="quote_author">Said by</label>
                                    <input id="quote_author" type="text" name="quote_author" maxlength="150" class="mt-1.5 w-full" placeholder="Nelson Mandela" value="{{ old('quote_author', $website->quote_author) }}">
                                    @error('quote_author')<p class="field-error">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label class="field-label" for="quote_author_role">Their role</label>
                                    <input id="quote_author_role" type="text" name="quote_author_role" maxlength="120" class="mt-1.5 w-full" placeholder="Former President of South Africa" value="{{ old('quote_author_role', $website->quote_author_role) }}">
                                    @error('quote_author_role')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Still uploadable, but named for where it actually
                             shows now. The About section used to carry a large
                             framed photograph beside the prose; that is gone
                             and the section reads down the middle instead, so
                             this picture's remaining home is the Contact
                             panel. Saying "shown beside the text" here would
                             be describing a card that no longer exists. --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            @if ($website->aboutImageUrl())
                                <img src="{{ $website->aboutImageUrl() }}" alt="" class="h-20 w-32 shrink-0 rounded-[8px] border border-gray-200 object-cover dark:border-gray-700">
                            @else
                                <span class="flex h-20 w-32 shrink-0 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-center text-xs font-semibold text-gray-500 dark:border-gray-600 dark:text-gray-400">No photo</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <label class="field-label" for="about_image">Photograph for the <strong>Contact</strong> section</label>
                                <input id="about_image" type="file" name="about_image" accept=".jpg,.jpeg,.png,.webp" class="mt-1.5 w-full text-sm">
                                <small class="field-hint mt-1">
                                    <strong>This is not the About background.</strong>
                                    For the picture behind the About section, use <em>About section background</em> at the top of this tab.
                                    This one is shown in the Contact section. A photograph of your pupils or campus works best.
                                    Leave blank to keep the current one. If you upload none, the Contact section borrows whichever image your hero is showing. JPEG, PNG or WebP.
                                </small>
                                @error('about_image')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save About Section</button>
                        </div>
                    </form>
                </div>
            </div>

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
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'intro', 'label' => 'Intro Subtitle', 'height' => '90px', 'description' => 'The line under the page title. One sentence saying what this page covers.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'body', 'label' => 'About Text', 'height' => '220px', 'description' => 'The main body of your About Us page: your history, your approach, and what a parent should know about the school.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'slogan', 'label' => 'Slogan Callout', 'height' => '90px', 'description' => 'A short highlighted line at the end, usually your motto. Leave it empty if you would rather not have one.'])
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
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'intro', 'label' => 'Intro', 'height' => '100px', 'description' => 'A welcome for prospective parents, and when your admissions window opens and closes.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'steps', 'label' => 'Application Steps (cards)', 'height' => '280px', 'description' => 'What a parent has to do, in order, with one card per step. Number them so it is obvious which comes first.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'requirements', 'label' => 'Requirements List', 'height' => '260px', 'description' => 'The documents and details a parent must bring or send. Being specific here saves your office a great many phone calls.'])
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
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'cards', 'label' => 'Contact Info Cards', 'height' => '220px', 'description' => 'Cards for things the fields below do not cover, such as office opening hours, a second campus, or who to ask for. Your email, phone and address are set under Contact Details and appear on their own.'])
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

                    <form method="POST" action="{{ route('website.update-contact-fields') }}" class="space-y-2">
                        @csrf
                        @method('PUT')

                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Contact Details</h3>
                        <small class="field-hint max-w-2xl">These are used everywhere your details appear: the top bar, the Contact section, the footer and the Contact Us page. Entered once here rather than typed into each. The email and phone become links a parent can tap on a phone, and the address is what the map points at.</small>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-text-field name="contact_email" label="Contact Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" :value="$website->contact_email" helper="Optional. The address enquiries should reach, usually your school office, not a personal account." />
                            <x-text-field name="contact_phone" label="Contact Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" :value="$website->contact_phone" helper="Optional. Shown as a number a visitor can tap to call. Include the country or area code." />
                            <x-text-field name="contact_address" label="Address" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" :value="$website->contact_address" helper="Optional. Your street address. This is also what the map on the Contact section searches for, so write it as you would for a delivery." />
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-text-field name="facebook_url" label="Facebook URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="$website->facebook_url" helper="Optional. The full web address of your page. Leave blank and no Facebook icon is shown." />
                            <x-text-field name="twitter_url" label="Twitter / X URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="$website->twitter_url" helper="Optional. The full web address of your profile. Leave blank and no X icon is shown." />
                            <x-text-field name="instagram_url" label="Instagram URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" :value="$website->instagram_url" helper="Optional. The full web address of your profile. Leave blank and no Instagram icon is shown." />
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
                    {{-- The newsletter box was removed from the footer; this
                         still promised it. --}}
                    <p class="mt-0.5 text-xs text-blue-600/80 dark:text-blue-400/70">Shown at the bottom of every page. The Quick Links and Academics columns are built from your website automatically and aren't edited here.</p>
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
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'description', 'label' => 'School Description', 'height' => '80px', 'description' => 'A sentence or two about the school, in the first footer column beneath your logo.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'contact', 'label' => 'Contact Lines', 'height' => '100px', 'description' => 'Extra lines for the footer contact column, such as a postal address or office hours. Your main email, phone and address are already shown from the Contact tab.'])
                                @include('school-admin.website._section-canvas', ['sectionKey' => 'cta', 'label' => 'Call-to-Action Banner', 'height' => '100px', 'description' => 'The wide band just above the footer, inviting a visitor to apply or get in touch. Add a button block to give them somewhere to go.'])
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
                        <small class="field-hint mt-0.5 max-w-md">The links across the top of your website. Leave this empty and the standard menu is used. Add even one link and it replaces the standard menu entirely, so add all of them.</small>
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
                        <p class="rounded-[8px] border border-dashed border-gray-300 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No custom links yet. The default menu (Home, About, Academics, Admissions, News, Events, Gallery, Contact) is being used.</p>
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
                        class="mt-4 space-y-2"
                    >
                        @csrf
                        <template x-if="navLinkEditing"><input type="hidden" name="_method" value="PUT"></template>

                        <x-text-field name="label" label="Label" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="navLinkEditing ? navLinkEditing.label : ''" placeholder="e.g. Alumni" required helper="The wording shown in the menu. One or two words, because the menu has to fit on a phone." />
                        <x-text-field name="url" label="URL" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="navLinkEditing ? navLinkEditing.url : ''" placeholder="e.g. /schools/your-school/gallery or #contact" required helper="Where the link goes. Start with # to scroll to a section of your home page (#about, #contact), or paste a full web address to send visitors elsewhere." />

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
                    <small class="field-hint mt-0.5 max-w-md">Photographs shown in the Gallery section of your home page, three at a time. Visitors can open any one full-screen and page through the rest from there, so add as many as you like.</small>
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
                <form method="POST" action="{{ route('website.gallery.store') }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                    @csrf
                    <div>
                        <label class="field-label" for="gallery_image">Image</label>
                        <input id="gallery_image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required class="mt-1.5 w-full">
                        <small class="field-hint mt-1">One photograph for the Gallery on your home page. Visitors can open it full-screen and page through the whole gallery from there. JPEG, PNG or WebP.</small>
                    </div>
                    <x-text-field name="caption" label="Caption" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Optional. A short line saying what the photograph shows. It appears under the image in the gallery and in the full-screen viewer." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="galleryOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
