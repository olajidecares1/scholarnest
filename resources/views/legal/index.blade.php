<x-legal-layout
    title="Legal"
    heading="Legal documents"
    summary="The agreement schools enter into with ScholarNest, and how the platform handles the information entrusted to it."
>
    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($documents as $document)
            <li>
                <a
                    href="{{ route('legal.show', $document->slug) }}"
                    class="flex h-full flex-col rounded-[10px] border border-[#DBE4F3] bg-white p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md"
                >
                    <span class="text-[16px] font-bold leading-snug">{{ $document->title }}</span>
                    @if ($document->summary)
                        <span class="mt-1.5 text-[13px] leading-relaxed text-[#4A648F]">{{ $document->summary }}</span>
                    @endif
                    <span class="mt-3 text-[11.5px] text-[#8DA2C4]">
                        Version {{ $document->version }} &middot; updated {{ $document->updated_at->format('j M Y') }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    <p class="mt-8 text-[13px] leading-relaxed text-[#6E85AC]">
        Questions about any of these, or about the information ScholarNest holds about you or your school, can be sent to our
        privacy contact &mdash; the address is in each document.
    </p>
</x-legal-layout>
