@props(['sectionKey', 'label', 'height' => '200px', 'description' => null])

<div>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</h3>
            @if ($description)
                {{-- What this strip of the page IS, before anyone starts
                     dragging blocks around in it. The three buttons opposite
                     add things to it; without this, the only clue to what a
                     section is for is its two-word heading. --}}
                <small class="field-hint mt-0.5 max-w-md">{{ $description }}</small>
            @endif
        </div>
        <div class="flex shrink-0 gap-1" x-show="! preview">
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'text')" class="inline-flex items-center gap-2 rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"><i class="fa-solid fa-plus" aria-hidden="true"></i> Text</button>
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'button')" class="inline-flex items-center gap-2 rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"><i class="fa-solid fa-plus" aria-hidden="true"></i> Button</button>
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'card')" class="inline-flex items-center gap-2 rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"><i class="fa-solid fa-plus" aria-hidden="true"></i> Card</button>
        </div>
    </div>
    <div class="mt-2">
        <x-website-blocks :blocks="[]" :editable="true" :section="$sectionKey" :height="$height" />
    </div>
</div>
