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

            {{-- Every network the school has filled in, from its own settings,
                 see App\Support\SchoolSocialLinks. --}}
            @php $socialLinks = \App\Support\SchoolSocialLinks::for($school); @endphp

            @if ($socialLinks !== [])
                <div class="mt-10 text-center">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Follow Us</p>
                    <div class="mt-3 flex flex-wrap justify-center gap-3">
                        @foreach ($socialLinks as $link)
                            <a
                                href="{{ $link['url'] }}"
                                target="_blank"
                                rel="noopener"
                                title="{{ $link['label'] }}"
                                aria-label="{{ $link['label'] }}"
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition-colors duration-150 hover:bg-primary-600 hover:text-white"
                            >
                                <i class="{{ $link['icon'] }} text-base"></i>
                            </a>
                        @endforeach
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

            {{-- Where the school actually is.

                 This page is the one a visitor opens looking for exactly that,
                 and it carried no address and no map at all, only the home
                 page had one. Same component as the home page, so the two
                 cannot disagree, and it draws nothing when no address has been
                 set. --}}
            @php($pageContact = \App\Support\SchoolContact::for($school))

            @if (filled($pageContact->address))
                <div class="mt-12">
                    <h2 class="text-center text-lg font-extrabold text-gray-900">Find Us</h2>

                    <div class="mx-auto mt-5 max-w-3xl">
                        <x-school-map :school="$school" :height="320" :show-address="true" />
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
