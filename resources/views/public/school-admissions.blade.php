@php
    $admissionsBlocks = $school->websiteBlocksFor('admissions')->groupBy('section');
@endphp

<x-public-site-layout :school="$school" :website="$website" title="Admissions" :used-fonts="\App\Support\GoogleFonts::usedInBlocks($admissionsBlocks->flatten(1))">
    <section class="bg-gray-900">
        <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6">
            <p class="text-xs font-bold uppercase tracking-wide text-primary-300">Admissions</p>
            <h1 class="mt-2 text-3xl font-extrabold text-white sm:text-4xl">Join {{ $school->name }}</h1>
            @if ($admissionsBlocks->has('intro'))
                <div class="relative mx-auto mt-4 max-w-2xl">
                    <x-website-blocks :blocks="$admissionsBlocks->get('intro')" height="90px" />
                </div>
            @endif
            <a href="#contact" class="mt-6 inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-6 py-3 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-lg">
                Apply for Admission
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        </div>
    </section>

    @if ($admissionsBlocks->has('steps'))
        <section class="px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-center text-2xl font-extrabold text-gray-900">Application Process</h2>
                <div class="relative mt-10">
                    <x-website-blocks :blocks="$admissionsBlocks->get('steps')" height="260px" />
                </div>
            </div>
        </section>
    @endif

    @if ($admissionsBlocks->has('requirements'))
        <section class="border-t border-gray-100 bg-gray-50 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-3xl">
                <h2 class="text-center text-2xl font-extrabold text-gray-900">Admission Requirements</h2>
                <div class="relative mx-auto mt-8 max-w-xl">
                    <x-website-blocks :blocks="$admissionsBlocks->get('requirements')" height="{{ $admissionsBlocks->get('requirements')->count() * 60 }}px" />
                </div>
            </div>
        </section>
    @endif

    @if ($school->academicLevels->isNotEmpty())
        <section class="px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-4xl text-center">
                <h2 class="text-2xl font-extrabold text-gray-900">Academic Levels We Admit</h2>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    @foreach ($school->academicLevels as $level)
                        <span class="rounded-full border border-primary-200 bg-primary-50 px-4 py-1.5 text-sm font-semibold text-primary-700">{{ $level->name }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-public-site-layout>
