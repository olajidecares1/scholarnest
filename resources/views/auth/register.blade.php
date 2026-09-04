@php
    $platformSettings = \App\Models\Setting::current();
    $logoUrl = $platformSettings->logo_path
        ? \Illuminate\Support\Facades\Storage::url($platformSettings->logo_path)
        : asset('images/logo-icon-dark.png');

    $revealClicks = (int) config('super_admin.reveal_clicks', 5);
    $revealTimeout = (int) config('super_admin.reveal_click_timeout', 2000);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Register your school - {{ config('app.name', 'ScholarNest') }}</title>

        <x-favicon />

        {{-- Inter, in the weights this page actually uses: 400 body, 500/600
             labels, 700/800 headings. Loading only these keeps the request
             small enough not to delay first paint. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        {{-- Font Awesome. Required here, not optional: the hidden Super Admin
             sign-in dialog on this page is drawn entirely with fa-* icons, and
             without this stylesheet they render as blank space. Every other
             auth screen inherits it from the shared layout; this page is
             standalone and has to load it itself. --}}

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>{!! \App\Support\ThemePreset::cssVariables($platformSettings->theme_preset) !!}</style>
    </head>

    <body
        class="min-h-dvh bg-[#F1F5FC] font-sans text-[#0F2A5C] antialiased lg:h-dvh lg:overflow-hidden"
        x-data="{
            clicks: 0,
            timer: null,
            // A failed attempt redirects back here, so the dialog reopens to
            // show why - otherwise the error lands on a page with nothing
            // visible to attach it to.
            open: {{ $errors->has('login') ? 'true' : 'false' }},
            registerClick() {
                // Each click must land within the timeout of the one before
                // it. Pause too long and the count starts over, so the
                // sequence has to be deliberate rather than something a
                // visitor drifts into over a few minutes.
                clearTimeout(this.timer);
                this.clicks++;

                if (this.clicks >= {{ $revealClicks }}) {
                    this.clicks = 0;
                    this.open = true;
                    this.$nextTick(() => this.$refs.superAdminLogin?.focus());
                    return;
                }

                this.timer = setTimeout(() => { this.clicks = 0 }, {{ $revealTimeout }});
            },
            close() {
                this.open = false;
                this.clicks = 0;
                clearTimeout(this.timer);
            },
        }"
        @keydown.escape.window="close()"
    >
        {{-- The background the Super Admin has configured, if any. Fixed
             behind everything and washed out, so it decorates the page without
             competing with the form on top of it. pointer-events-none keeps it
             from swallowing clicks meant for the card. --}}
        @if ($background)
            <div class="pointer-events-none fixed inset-0">
                @if ($background->type->value === 'video')
                    <video src="{{ $background->url() }}" autoplay muted loop playsinline class="h-full w-full object-cover"></video>
                @else
                    <img src="{{ $background->url() }}" alt="" class="h-full w-full object-cover">
                @endif
                <div class="absolute inset-0 bg-[#F1F5FC]/85"></div>
            </div>
        @endif

        {{-- Exactly one viewport tall from the "lg" breakpoint up, where the
             two columns sit side by side and the card is meant to be seen
             whole. dvh rather than vh because on mobile browsers 100vh is the
             height with the address bar HIDDEN, so a vh-sized layout is always
             slightly taller than what is actually on screen.

             Below "lg" the columns stack and the form alone is taller than a
             phone, so the page keeps its natural height there - pinning it to
             the viewport would clip the submit button off the bottom. --}}
        <div class="relative min-h-dvh w-full lg:h-dvh lg:min-h-0">
            <div class="grid min-h-dvh w-full bg-white lg:h-full lg:min-h-0 lg:grid-cols-2">

                {{-- ── Left: brand panel ─────────────────────────────────── --}}
                <div class="relative flex flex-col overflow-hidden bg-white">
                    <div class="shrink-0 px-8 pt-5 sm:px-10 sm:pt-6">
                        {{-- The logo alone, top-left. No wordmark beside it:
                             the mark already carries "ScholarNest", and setting
                             the name twice within 200px reads as a mistake.

                             It is also the click target for the hidden Super
                             Admin sign-in, so it must not navigate - the first
                             click would leave the page and the sequence could
                             never reach five. Nothing here announces itself:
                             no title, no cursor change. --}}
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ config('app.name', 'ScholarNest') }}"
                            @click="registerClick()"
                            class="h-20 w-20 shrink-0 select-none rounded-[10px] object-contain"
                        >

                        <h1 class="mt-4 text-[26px] font-extrabold leading-[1.15] tracking-tight text-[#0F2A5C] sm:text-[30px]">
                            Create Your<br>School Account
                        </h1>

                        <p class="mt-3 max-w-sm text-[12.5px] leading-[1.65] text-[#5B7099]">
                            Join thousands of schools using ScholarNest to manage operations, engage students, and grow together.
                        </p>
                    </div>

                    {{-- min-h-0 lets this actually shrink. A flex child defaults
                         to min-height:auto, which refuses to go below its
                         content and would push the feature band off the bottom
                         on a short window instead of the illustration giving
                         way. --}}
                    <div class="mt-3 flex min-h-0 flex-1 items-center justify-center overflow-hidden px-6 sm:px-8">
                        <x-auth-panel-illustration />
                    </div>

                    {{-- Feature band. Sits flush to the bottom of the panel so
                         it reads as a footer to the illustration above it. --}}
                    <div class="mt-auto shrink-0 bg-primary-500 px-6 py-4 text-white sm:px-8">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            @foreach ([
                                ['Secure &amp; Safe', 'Your data is protected with enterprise-grade security.', 'M12 3l7 3v6c0 4.4-3 8.2-7 9-4-.8-7-4.6-7-9V6l7-3z'],
                                ['Built for Schools', 'Everything you need to manage your school in one place.', 'M9 11a3 3 0 100-6 3 3 0 000 6zM3 20c0-3 2.7-5 6-5s6 2 6 5M17 20c0-2.3-.9-4-2.5-5M16 11a3 3 0 000-6'],
                                ['Smart &amp; Reliable', 'Powerful tools to help you make better decisions.', 'M7 16V9M12 16V5M17 16v-4'],
                            ] as [$title, $body, $path])
                                <div class="flex flex-col items-center">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full border-[1.5px] border-white/60">
                                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="{{ $path }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                    <p class="mt-2 text-[11.5px] font-bold leading-tight">{!! $title !!}</p>
                                    <p class="mt-1 text-[10px] leading-[1.45] text-white/85">{{ $body }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ── Right: the form ───────────────────────────────────── --}}
                {{-- Sized to fit a viewport without scrolling. The overflow is
                     a safety net for genuinely short windows - a laptop at
                     1366x600, a phone in landscape - where the alternative
                     would be clipping the submit button. no-scrollbar hides
                     the bar itself, so the safety net never shows. --}}
                <div class="no-scrollbar flex items-center justify-center overflow-y-auto px-6 py-4 sm:px-10 sm:py-5">
                    <div class="my-auto w-full max-w-[370px]">
                        <div class="text-center">
                            <span class="mx-auto flex h-[44px] w-[44px] items-center justify-center rounded-full bg-[#EAF2FF]">
                                <svg class="h-[22px] w-[22px] text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 21h18M5 21V9l7-5 7 5v12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M10 21v-5h4v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M9.5 11h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                </svg>
                            </span>

                            <h2 class="mt-2.5 text-[19px] font-bold tracking-tight text-[#0F2A5C]">Register Your School</h2>
                            <p class="mt-1 text-[11.5px] text-[#64789F]">Fill in the details below to create your school account.</p>
                        </div>

                        @if ($errors->any() && ! $errors->has('login'))
                            <div class="mt-4 rounded-[9px] bg-red-50 p-3 text-[12px] text-red-700">
                                <ul class="list-inside list-disc space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register') }}" class="mt-3 space-y-[8px]" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <x-honeypot />

                            @foreach ([
                                ['school_name', 'School Name', 'text', 'Enter your school name', 'organization', 'M4 21h16M6 21V8l6-4 6 4v13M10 21v-4h4v4', true],
                                ['email', 'School Email Address', 'email', 'Enter school email address', 'email', 'M3 7l9 6 9-6M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z', false],
                                ['phone', 'Phone Number', 'tel', 'Enter phone number', 'tel', 'M6.5 3h3l1.5 4-2 1.5a12 12 0 005.5 5.5L16 12l4 1.5v3a2 2 0 01-2.2 2A16.5 16.5 0 014.5 5.2 2 2 0 016.5 3z', false],
                            ] as [$name, $label, $type, $placeholder, $autocomplete, $iconPath, $autofocus])
                                <div>
                                    <label for="{{ $name }}" class="field-label">
                                        {{ $label }} <span class="text-red-500">*</span>
                                    </label>

                                    <div class="relative mt-1">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="{{ $iconPath }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>

                                        <input
                                            id="{{ $name }}"
                                            name="{{ $name }}"
                                            type="{{ $type }}"
                                            value="{{ old($name) }}"
                                            placeholder="{{ $placeholder }}"
                                            autocomplete="{{ $autocomplete }}"
                                            required
                                            @if ($autofocus) autofocus @endif
                                            class="w-full pl-9 pr-3"
                                        >
                                    </div>

                                    <x-input-error :messages="$errors->get($name)" class="mt-1" />
                                </div>
                            @endforeach

                            @foreach ([
                                ['password', 'Password', 'Create a password'],
                                ['password_confirmation', 'Confirm Password', 'Confirm your password'],
                            ] as [$name, $label, $placeholder])
                                <div x-data="{ show: false }">
                                    <label for="{{ $name }}" class="field-label">
                                        {{ $label }} <span class="text-red-500">*</span>
                                    </label>

                                    <div class="relative mt-1">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect x="5" y="10.5" width="14" height="9.5" rx="2" stroke="currentColor" stroke-width="1.6" />
                                                <path d="M8.5 10.5V7.75a3.5 3.5 0 017 0v2.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                            </svg>
                                        </span>

                                        <input
                                            id="{{ $name }}"
                                            name="{{ $name }}"
                                            :type="show ? 'text' : 'password'"
                                            type="password"
                                            placeholder="{{ $placeholder }}"
                                            autocomplete="new-password"
                                            required
                                            class="w-full pl-9 pr-9"
                                        >

                                        <button
                                            type="button"
                                            @click="show = ! show"
                                            :aria-label="show ? 'Hide password' : 'Show password'"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#9AAAC4] transition hover:text-[#5B7099]"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
                                                <path x-show="show" x-cloak d="M4 20L20 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                    </div>

                                    <x-input-error :messages="$errors->get($name)" class="mt-1" />
                                </div>
                            @endforeach

                            {{-- The agreement.

                                 THE LINKS OPEN IN A NEW TAB, deliberately. This
                                 form is long, and sending someone away from it
                                 to read the Terms would lose everything they
                                 have typed - which is how an agreement checkbox
                                 ends up being ticked without being read.

                                 rel="noopener" because target="_blank" otherwise
                                 hands the opened page a reference back to this
                                 one through window.opener.

                                 The `required` attribute below is a courtesy to
                                 the person filling the form in, NOT the rule.
                                 The rule is 'terms' => ['accepted'] in
                                 RegisterSchoolRequest, which runs on the server
                                 and rejects a submission with the box unticked,
                                 absent, or set to anything other than a true
                                 value - including one made with the attribute
                                 stripped out or the form posted directly. --}}
                            <div class="pt-0.5">
                                <label class="flex items-start gap-2 text-[11.5px] leading-[1.55] text-[#3D5378]">
                                    <input
                                        type="checkbox"
                                        name="terms"
                                        value="1"
                                        @checked(old('terms'))
                                        required
                                        class="mt-[1px] w-[15px] shrink-0 text-primary-500"
                                    >
                                    <span>
                                        I have read and agree to the
                                        <a href="{{ route('legal.show', 'terms') }}" target="_blank" rel="noopener" class="font-medium text-primary-500 hover:underline">Terms &amp; Conditions</a>,
                                        the <a href="{{ route('legal.show', 'privacy') }}" target="_blank" rel="noopener" class="font-medium text-primary-500 hover:underline">Privacy Policy</a> and
                                        the <a href="{{ route('legal.show', 'cookies') }}" target="_blank" rel="noopener" class="font-medium text-primary-500 hover:underline">Cookie Policy</a>,
                                        and I confirm I am authorised to accept them for this school.
                                        <span class="text-red-500">*</span>
                                    </span>
                                </label>
                                <x-input-error :messages="$errors->get('terms')" class="mt-1" />
                            </div>

                            {{-- Disabled on the form's submit event, not the
                                 button's click: disabling a submit button
                                 inside its own click handler cancels the
                                 submission in most browsers. --}}
                            <button
                                type="submit"
                                :disabled="submitting"
                                class="mt-1 h-[40px] w-full rounded-[8px] bg-primary-500 text-[13px] font-bold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                <span x-show="! submitting">Create School Account</span>
                                <span x-show="submitting" x-cloak>Creating your account&hellip;</span>
                            </button>
                        </form>

                        {{-- The way back in.

                             This page had no sign-in link at all, which is how
                             two schools ended up entering perfectly good
                             credentials into the ScholarNest Team dialog: they
                             had already registered, came back here, found
                             nothing to click, and went looking. The one login
                             form on this page was the hidden one.

                             Goes to the finder, not to a login form: a school
                             names itself, then picks the portal it wants from
                             the ones its plan includes. route('login') would
                             have redirected straight back to this page. --}}
                        <p class="mt-3 text-center text-[12px] text-[#64789F]">
                            Already registered?
                            <a
                                href="{{ route('portal.find.show') }}"
                                class="font-bold text-primary-500 underline-offset-2 hover:text-primary-600 hover:underline"
                            >Go to your school's portal</a>
                        </p>

                        <p class="mt-2.5 flex items-center justify-center gap-1.5 text-[10.5px] text-[#8194B3]">
                            <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="10.5" width="14" height="9.5" rx="2" stroke="currentColor" stroke-width="1.7" />
                                <path d="M8.5 10.5V7.75a3.5 3.5 0 017 0v2.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            </svg>
                            Your information is safe with us. We never share your data.
                        </p>

                    </div>
                </div>
            </div>
        </div>

        <x-super-admin-login-dialog />
    </body>
</html>
