@php
    $contactBlocks = $school->websiteBlocksFor('contact')->groupBy('section');
@endphp

<x-public-site-layout :school="$school" :website="$website" title="Contact Us" :used-fonts="\App\Support\GoogleFonts::usedInBlocks($contactBlocks->flatten(1))">
    <section class="bg-gray-900">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
            <p class="text-xs font-bold uppercase tracking-wide text-primary-300">Contact Us</p>
            <h1 class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">Get in Touch with {{ $school->name }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-gray-300">We'd love to hear from you. Reach out with any questions about admissions, academics, or campus life.</p>
        </div>
    </section>

    <section class="px-4 py-16 sm:px-6">
        <div class="mx-auto max-w-5xl">
            @if ($contactBlocks->has('cards'))
                <div class="relative">
                    <x-website-blocks :blocks="$contactBlocks->get('cards')" height="220px" />
                </div>
            @else
                <p class="text-center text-sm text-gray-500">This school hasn't added contact details yet.</p>
            @endif

            @if ($website->facebook_url || $website->twitter_url || $website->instagram_url)
                <div class="mt-10 text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Follow Us</p>
                    <div class="mt-3 flex justify-center gap-3">
                        @if ($website->facebook_url)
                            <a href="{{ $website->facebook_url }}" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V8c0-.9.25-1.5 1.55-1.5H16.7V3.7C16.4 3.65 15.4 3.55 14.25 3.55c-2.4 0-4.05 1.45-4.05 4.15v2.25H7.5v3.1h2.7v8h3.3z" /></svg>
                            </a>
                        @endif
                        @if ($website->twitter_url)
                            <a href="{{ $website->twitter_url }}" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6.5c-.6.3-1.3.5-2 .6.7-.4 1.3-1.2 1.5-2-.7.4-1.5.7-2.3.9a3.5 3.5 0 00-6 3.2A10 10 0 014 5.9a3.5 3.5 0 001.1 4.7c-.5 0-1-.2-1.5-.4v.1c0 1.7 1.2 3.1 2.8 3.4-.5.1-1 .2-1.6.1.4 1.4 1.7 2.4 3.3 2.4A7 7 0 014 17.5a10 10 0 005.4 1.6c6.5 0 10-5.4 10-10v-.5c.7-.5 1.3-1.1 1.7-1.8z" /></svg>
                            </a>
                        @endif
                        @if ($website->instagram_url)
                            <a href="{{ $website->instagram_url }}" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-colors duration-150 hover:bg-primary-600 hover:text-white">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.6" /><circle cx="12" cy="12" r="3.7" stroke="currentColor" stroke-width="1.6" /><circle cx="17" cy="7" r="1" fill="currentColor" /></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($website->contact_email)
                <div class="mt-10 text-center">
                    <a href="mailto:{{ $website->contact_email }}" class="inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-6 py-3 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-lg">
                        Send Us a Message
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
