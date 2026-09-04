<x-legal-layout
    :title="$document->title"
    :heading="$document->title"
    :summary="$document->summary"
    :version="$document->version"
    :lastUpdated="$document->updated_at->format('j F Y')"
>
    {{-- The document itself.

         {!! !!} rather than {{ }} because this is rendered markdown, not user
         input: the source is written by the ScholarNest Team in the admin screens,
         and it was converted with raw HTML stripped. Nothing a school or a
         visitor types reaches this page.

         The styling is in app.css rather than in the document, so the markdown
         stays plain and reviewable by a lawyer who should not have to read
         around class attributes. --}}
    <article class="legal-prose">
        {!! $document->renderedHtml() !!}
    </article>

    <nav aria-label="Other documents" class="mt-12 border-t border-[#DBE4F3] pt-7">
        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#6E85AC]">Also worth reading</p>
        <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2">
            @foreach ($others as $other)
                @continue($other->slug === $document->slug)
                <li>
                    <a href="{{ route('legal.show', $other->slug) }}" class="text-[13.5px] font-semibold text-primary-600 hover:underline">
                        {{ $other->title }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</x-legal-layout>
