@props(['sectionKey', 'label', 'height' => '200px'])

<div>
    <div class="flex items-center justify-between">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</h3>
        <div class="flex gap-1" x-show="! preview">
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'text')" class="rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20">+ Text</button>
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'button')" class="rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20">+ Button</button>
            <button type="button" @click="addBlock('{{ $sectionKey }}', 'card')" class="rounded-[6px] px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20">+ Card</button>
        </div>
    </div>
    <div class="mt-2">
        <x-website-blocks :blocks="[]" :editable="true" :section="$sectionKey" :height="$height" />
    </div>
</div>
