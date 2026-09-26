{{-- Shared by the School Admin panel and the Class Teacher portal, which are
     allowed different things. Each capability is a prop that defaults to
     true, the admin's page says nothing and keeps everything, the teacher's
     turns off what it must.

     These props only decide what is DRAWN. What can be reached is decided by
     the routes: the Class Teacher portal has no print route and no pdf route
     to call, so passing canPrint here is a tidiness, not the control. --}}
@props([
    'canSend' => true,
    'canPrint' => true,
    'canDownload' => true,
])

<div
    x-data="{}"
    x-show="$store.resultPreview.open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="$store.resultPreview.close()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
    style="display: none;"
>
    <div
        @click.outside="$store.resultPreview.close()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-[10px] bg-white shadow-2xl dark:bg-gray-800"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Student Result</h3>
                <small class="block truncate text-xs font-mono text-gray-500 dark:text-gray-400" x-show="$store.resultPreview.cardNumber" x-text="$store.resultPreview.cardNumber"></small>
            </div>
            <button type="button" @click="$store.resultPreview.close()" class="shrink-0 rounded-[8px] p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200">
                <i class="fa-solid fa-xmark text-[17px] leading-none" aria-hidden="true"></i>
            </button>
        </div>

        <div class="flex items-center gap-1 border-b border-gray-100 px-5 pt-3 dark:border-gray-700">
            <button type="button" @click="$store.resultPreview.tab = 'details'" :class="$store.resultPreview.tab === 'details' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-3 pb-2.5 text-xs font-semibold transition-colors duration-200">Details</button>
            <button type="button" @click="$store.resultPreview.showReportCard()" :class="$store.resultPreview.tab === 'report-card' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-3 pb-2.5 text-xs font-semibold transition-colors duration-200">Preview Report Card</button>
            @if ($canSend)
                <button type="button" @click="$store.resultPreview.tab = 'send'" :class="$store.resultPreview.tab === 'send' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-3 pb-2.5 text-xs font-semibold transition-colors duration-200">Send Result</button>
            @endif
        </div>

        <div class="flex-1 overflow-y-auto bg-gray-50 p-5 dark:bg-gray-900/40">
            <template x-if="$store.resultPreview.loading">
                <div class="flex items-center justify-center gap-2 py-16 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" /></svg>
                    Loading result&hellip;
                </div>
            </template>

            <template x-if="!$store.resultPreview.loading && $store.resultPreview.error">
                <p class="py-6 text-center text-sm font-semibold text-red-600" x-text="$store.resultPreview.error"></p>
            </template>

            <div x-show="!$store.resultPreview.loading && !$store.resultPreview.error">
                <div x-show="$store.resultPreview.tab === 'details'" x-html="$store.resultPreview.detailsHtml"></div>

                <div x-show="$store.resultPreview.tab === 'report-card'" style="display: none;" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-center gap-2 rounded-[8px] bg-gray-100 p-2 dark:bg-gray-900/40">
                        <button type="button" @click="$store.resultPreview.zoomOut()" class="flex h-7 w-7 items-center justify-center rounded-[6px] border border-gray-300 bg-white text-sm font-bold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">&minus;</button>
                        <button type="button" @click="$store.resultPreview.zoomReset()" class="w-14 rounded-[6px] px-1 py-1 text-center text-xs font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700" x-text="Math.round($store.resultPreview.zoom * 100) + '%'"></button>
                        <button type="button" @click="$store.resultPreview.zoomIn()" class="flex h-7 w-7 items-center justify-center rounded-[6px] border border-gray-300 bg-white text-sm font-bold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">+</button>
                        <span class="mx-0.5 h-4 w-px bg-gray-300 dark:bg-gray-600"></span>
                        <button type="button" @click="$store.resultPreview.fitToScreen()" class="btn rounded-[6px] border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">Fit to Screen</button>
                    </div>
                    <div id="result-report-card-container" class="flex justify-center overflow-auto rounded-[8px] py-2" style="max-height: 70vh;">
                        <div id="result-report-card-content" :style="'transform: scale(' + $store.resultPreview.zoom + '); transform-origin: top center; transition: transform 150ms ease-out;'" x-html="$store.resultPreview.reportCardHtml"></div>
                    </div>
                </div>

                @if ($canSend)
                <div x-show="$store.resultPreview.tab === 'send'" style="display: none;" class="mx-auto max-w-sm space-y-4 py-4 text-left">
                    <small class="block text-sm text-gray-600 dark:text-gray-300">Choose who should receive this student's complete result and report card.</small>

                    <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 p-3 text-sm dark:border-gray-700">
                        <input type="checkbox" x-model="$store.resultPreview.recipients.student" class="text-blue-600">
                        Student
                    </label>
                    <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 p-3 text-sm dark:border-gray-700" :class="$store.resultPreview.guardianCount === 0 ? 'opacity-50' : ''">
                        <input type="checkbox" x-model="$store.resultPreview.recipients.guardians" :disabled="$store.resultPreview.guardianCount === 0" class="text-blue-600">
                        <span>
                            Parent / Guardian
                            <small class="block text-xs text-gray-400" x-show="$store.resultPreview.guardianCount === 0">No guardian is linked to this student yet.</small>
                        </span>
                    </label>

                    <small class="block text-xs text-gray-400" x-show="$store.resultPreview.lastSentAt" x-text="'Last sent: ' + $store.resultPreview.lastSentAt"></small>

                    <template x-if="$store.resultPreview.sendMessage">
                        <p class="rounded-[8px] bg-green-50 p-3 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400" x-text="$store.resultPreview.sendMessage"></p>
                    </template>

                    <button
                        type="button"
                        @click="confirm('Send this result now? The recipient(s) will be notified immediately.') && $store.resultPreview.confirmSend()"
                        :disabled="$store.resultPreview.sending || (!$store.resultPreview.recipients.student && !$store.resultPreview.recipients.guardians)"
                        class="btn flex w-full items-center justify-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700 disabled:pointer-events-none disabled:opacity-50"
                    >
                        <span x-show="$store.resultPreview.sending">Sending&hellip;</span>
                        <span x-show="!$store.resultPreview.sending">Send Result</span>
                    </button>
                </div>
                @endif
            </div>
        </div>

        @if ($canPrint || $canDownload)
            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-5 py-4 dark:border-gray-700" x-show="$store.resultPreview.tab === 'report-card' && !$store.resultPreview.loading && !$store.resultPreview.error">
                @if ($canPrint)
                    <button
                        type="button"
                        @click="$store.resultPreview.print()"
                        :disabled="!$store.resultPreview.hasScores"
                        class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:pointer-events-none disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Print
                    </button>
                @endif

                @if ($canDownload)
                    <button
                        type="button"
                        @click="$store.resultPreview.download()"
                        :disabled="$store.resultPreview.downloading || !$store.resultPreview.hasScores"
                        class="btn flex min-w-[9rem] items-center justify-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700 disabled:pointer-events-none disabled:opacity-50"
                    >
                        <span x-show="$store.resultPreview.downloading">Downloading&hellip;</span>
                        <span x-show="!$store.resultPreview.downloading">Download PDF</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Hidden print target: the Print button sets this iframe's src directly to
the GET print URL, which auto-calls window.print() on load (same convention
as id-cards/print.blade.php), so the system print dialog opens without
navigating this page or opening a new tab.

Left out entirely where printing is not offered, a page with no print
capability should not carry the frame that performs one. --}}
@if ($canPrint)
    <iframe id="result-print-frame" name="result-print-frame" style="position: absolute; width: 0; height: 0; border: 0;" title="Report card print frame"></iframe>
@endif
