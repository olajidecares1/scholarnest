@props([
    'school',
    'height' => 260,
    'showAddress' => false,
])

@php
    $contact = \App\Support\SchoolContact::for($school);

    /*
     * The map is drawn from the ADDRESS, and only from the address.
     *
     * It used to fall back to the school's name when no address was set,
     * which produces a map of wherever Google decides that name is, a
     * different town, a similarly named school, or nothing. A map that is
     * confidently wrong about where a school is, on the school's own website,
     * is worse than no map: a parent drives to it.
     *
     * So no address means no map, and the school is told to add one rather
     * than shown a guess.
     */
    $mapQuery = $contact->address;
@endphp

@if (filled($mapQuery))
    <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[10px] border border-gray-200']) }} style="height: {{ $height }}px;">
        <iframe
            title="Map showing {{ $school->name }}"
            src="https://www.google.com/maps?q={{ urlencode($mapQuery) }}&output=embed"
            class="h-full w-full"
            style="height: {{ $height }}px; border: 0;"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>

    @if ($showAddress)
        <div class="mt-3 flex items-start gap-2.5">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-[6px] bg-primary-50 text-primary-700">
                <i class="fa-solid fa-location-dot text-[12px]" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <p class="text-[12.5px] font-bold text-gray-900">Our Address</p>
                <p class="text-[12.5px] leading-relaxed text-gray-700">{{ $contact->address }}</p>

                {{-- Opens the address in whatever map app the visitor has,
                     which is what somebody on a phone actually wants. --}}
                <a
                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode($contact->address) }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-1 inline-flex items-center gap-1 text-[12px] font-bold text-primary-700 hover:text-primary-800"
                >
                    Get directions
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @endif
@endif
