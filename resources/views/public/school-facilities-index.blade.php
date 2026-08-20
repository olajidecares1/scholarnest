<x-public-site-layout :school="$school" :website="$website" title="Facilities">
    <section class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-7xl">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Our Facilities</p>
                <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Built for Learning & Growth</h1>
            </div>

            @if ($facilities->isEmpty())
                <p class="mt-10 text-center text-sm text-gray-500">Facility details coming soon.</p>
            @else
                <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($facilities as $facility)
                        <div class="overflow-hidden rounded-[10px] border border-gray-200 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
                            <div class="h-44 bg-gray-100">
                                @if ($facility->imageUrl())
                                    <img src="{{ $facility->imageUrl() }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-gray-300">
                                        <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20V10.5L12 4l8 6.5V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="p-5">
                                @if ($facility->category)
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-primary-600">{{ $facility->category }}</span>
                                @endif
                                <h2 class="mt-1 text-base font-bold text-gray-900">{{ $facility->name }}</h2>
                                @if ($facility->description)
                                    <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $facility->description }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
