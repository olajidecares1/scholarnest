@php
    $aboutBlocks = $school->websiteBlocksFor('about')->groupBy('section');
@endphp

<x-public-site-layout :school="$school" :website="$website" title="About Us" :used-fonts="\App\Support\GoogleFonts::usedInBlocks($aboutBlocks->flatten(1))">
    <section class="bg-gray-900">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
            <p class="text-xs font-bold uppercase tracking-wide text-primary-300">About Us</p>
            <h1 class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">Get to Know {{ $school->name }}</h1>
            @if ($aboutBlocks->has('intro'))
                <div class="relative mx-auto mt-4 max-w-2xl">
                    <x-website-blocks :blocks="$aboutBlocks->get('intro')" height="80px" />
                </div>
            @endif
        </div>
    </section>

    <section class="px-4 py-16 sm:px-6">
        <div class="mx-auto max-w-3xl text-center">
            @if ($aboutBlocks->has('body'))
                <div class="relative">
                    <x-website-blocks :blocks="$aboutBlocks->get('body')" height="220px" />
                </div>
            @else
                <p class="text-sm text-gray-500">This school hasn't added an About Us description yet.</p>
            @endif
        </div>
    </section>

    @if ($aboutBlocks->has('slogan'))
        <section class="bg-gray-900">
            <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
                <div class="relative">
                    <x-website-blocks :blocks="$aboutBlocks->get('slogan')" height="80px" />
                </div>
                @if ($website->slogan_tagline)
                    <p class="mt-3 text-sm font-semibold uppercase tracking-wide text-primary-300">{{ $website->slogan_tagline }}</p>
                @endif
            </div>
        </section>
    @endif

    @if ($school->academicLevels->isNotEmpty())
        <section class="px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-4xl text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">What We Offer</p>
                <h2 class="mt-2 text-2xl font-extrabold text-gray-900">Education for Every Stage of Growth</h2>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    @foreach ($school->academicLevels as $level)
                        <span class="rounded-full border border-primary-200 bg-primary-50 px-4 py-1.5 text-sm font-semibold text-primary-700">{{ $level->name }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-public-site-layout>
