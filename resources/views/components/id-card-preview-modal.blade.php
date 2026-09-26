@props(['viewOnly' => false])

<div
    x-data="{}"
    x-show="$store.idCardPreview.open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="$store.idCardPreview.close()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
    style="display: none;"
>
    <div
        @click.outside="$store.idCardPreview.close()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-[10px] bg-white shadow-2xl dark:bg-gray-800"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">ID Card Preview</h3>
                <small class="block truncate text-xs font-mono text-gray-500 dark:text-gray-400" x-show="$store.idCardPreview.cardNumber" x-text="$store.idCardPreview.cardNumber"></small>
            </div>
            <button type="button" @click="$store.idCardPreview.close()" class="shrink-0 rounded-[8px] p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                <i class="fa-solid fa-xmark text-[17px] leading-none" aria-hidden="true"></i>
            </button>
        </div>

        <div class="flex flex-1 flex-col items-center justify-center gap-4 overflow-auto bg-gray-50 p-6 dark:bg-gray-900/40" style="min-height: 22rem;">
            <template x-if="$store.idCardPreview.loading">
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" /></svg>
                    Loading card&hellip;
                </div>
            </template>

            <template x-if="!$store.idCardPreview.loading && $store.idCardPreview.error">
                <p class="text-sm font-semibold text-red-600" x-text="$store.idCardPreview.error"></p>
            </template>

            <template x-if="!$store.idCardPreview.loading && !$store.idCardPreview.error && !$store.idCardPreview.hasTemplate">
                <p class="text-sm text-amber-700">No template is available for this card type yet.</p>
            </template>

            <div x-show="!$store.idCardPreview.loading && !$store.idCardPreview.error && $store.idCardPreview.hasTemplate" class="flex flex-col items-center gap-4" style="display: none;">
                <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                    <button type="button" @click="$store.idCardPreview.switchSide('front')" :class="$store.idCardPreview.side === 'front' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-xs font-semibold transition-colors duration-200">Front</button>
                    <button type="button" @click="$store.idCardPreview.switchSide('back')" :class="$store.idCardPreview.side === 'back' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-xs font-semibold transition-colors duration-200">Back</button>
                </div>

                <div class="w-full overflow-auto rounded-[10px] py-6" id="id-card-preview-canvas">
                    <div class="flex min-w-full justify-center">
                        <div
                            class="origin-center transition-transform duration-200 ease-out"
                            :style="{ transform: 'scale(' + $store.idCardPreview.effectiveScale() + ')' }"
                            x-show="$store.idCardPreview.side === 'front'"
                            x-html="$store.idCardPreview.frontHtml"
                            id="id-card-preview-front"
                        ></div>
                        <div
                            class="origin-center transition-transform duration-200 ease-out"
                            :style="{ transform: 'scale(' + $store.idCardPreview.effectiveScale() + ')' }"
                            x-show="$store.idCardPreview.side === 'back'"
                            style="display: none;"
                            x-html="$store.idCardPreview.backHtml"
                            id="id-card-preview-back"
                        ></div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="$store.idCardPreview.zoomOut()" class="flex h-8 w-8 items-center justify-center rounded-[8px] border border-gray-300 text-lg leading-none text-gray-600 transition-colors duration-150 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">&minus;</button>
                    <button type="button" @click="$store.idCardPreview.resetZoom()" class="w-14 text-center text-xs font-semibold text-gray-500 dark:text-gray-400" x-text="Math.round($store.idCardPreview.zoom * 100) + '%'"></button>
                    <button type="button" @click="$store.idCardPreview.zoomIn()" class="flex h-8 w-8 items-center justify-center rounded-[8px] border border-gray-300 text-lg leading-none text-gray-600 transition-colors duration-150 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">+</button>
                </div>
            </div>
        </div>

        @unless($viewOnly)
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                <button
                    type="button"
                    @click="$store.idCardPreview.print()"
                    :disabled="$store.idCardPreview.loading || !$store.idCardPreview.hasTemplate"
                    class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:pointer-events-none disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Print
                </button>
                <button
                    type="button"
                    @click="$store.idCardPreview.download()"
                    :disabled="$store.idCardPreview.loading || $store.idCardPreview.downloading || !$store.idCardPreview.hasTemplate"
                    class="btn flex min-w-[9rem] items-center justify-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700 disabled:pointer-events-none disabled:opacity-50"
                >
                    <span x-show="$store.idCardPreview.downloading">Downloading&hellip;</span>
                    <span x-show="!$store.idCardPreview.downloading">Download PDF</span>
                </button>
            </div>
        @endunless
    </div>
</div>

@unless($viewOnly)
    {{-- Hidden print form/iframe: submitting loads id-cards.print INTO the iframe
    (not the main window) via its target attribute, so the browser's print dialog
    opens for that iframe's content without navigating this page or opening a
    new tab. print.blade.php already auto-calls window.print() on load. --}}
    <form id="id-card-print-form" method="POST" action="{{ route('id-cards.print') }}" target="id-card-print-frame" style="display: none;">
        @csrf
        <input type="hidden" id="id-card-print-type" name="type">
        <input type="hidden" id="id-card-print-record" name="records[]">
        <input type="hidden" id="id-card-print-template" name="template">
    </form>
    <iframe name="id-card-print-frame" style="position: absolute; width: 0; height: 0; border: 0;" title="ID card print frame"></iframe>
@endunless
