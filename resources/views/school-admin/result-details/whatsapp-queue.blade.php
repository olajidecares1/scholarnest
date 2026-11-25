{{-- Send on WhatsApp, one parent after another, from the School Admin's own
     phone. Each "Open WhatsApp" posts to details.whatsapp in a new tab, which
     marks the message sent and opens WhatsApp at that parent's chat with the
     message already typed. Back here, the next parent is waiting. --}}
@php
    $rowsJson = json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
@endphp

<x-dashboard-layout page-title="Send on WhatsApp" page-subtitle="{{ $title ?? 'Result details to parents, one after another, from your phone.' }}">
    <div class="mx-auto max-w-2xl space-y-5"
         x-data="{
            rows: {{ $rowsJson }},
            current: null,
            init() { this.current = this.nextFrom(0) },
            sendable(row) { return ! row.done && ! row.problem },
            nextFrom(start) {
                for (let i = start; i < this.rows.length; i++) { if (this.sendable(this.rows[i])) return i }
                for (let i = 0; i < start; i++) { if (this.sendable(this.rows[i])) return i }
                return null
            },
            get doneCount() { return this.rows.filter(r => r.done).length },
            get sendableCount() { return this.rows.filter(r => ! r.problem || r.done).length },
            get row() { return this.current === null ? null : this.rows[this.current] },
            opened(index) {
                this.rows[index].done = true
                setTimeout(() => { this.current = this.nextFrom(index + 1) }, 400)
            },
            skip() { this.current = this.nextFrom(this.current + 1) },
         }">

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('result-pins.details.history', array_filter(['batch' => $batch])) }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                <i class="fa-solid fa-chevron-left text-[12px]" aria-hidden="true"></i> Sending history
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 text-center text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                No WhatsApp messages are waiting to be sent.
            </div>
        @else
            {{-- How far along. --}}
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        <span x-text="doneCount"></span> of <span x-text="sendableCount"></span> parents sent
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">Free, from your own WhatsApp</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-full rounded-full bg-[#25D366] transition-all duration-300" :style="`width: ${sendableCount ? Math.round(doneCount / sendableCount * 100) : 0}%`"></div>
                </div>
            </div>

            {{-- The parent to send to now. --}}
            <template x-if="row">
                <div class="rounded-[10px] border-2 border-[#25D366] bg-white p-5 shadow-sm dark:bg-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">Next parent</p>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white" x-text="row.parent"></p>
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        <span x-text="row.student"></span><span x-show="row.admission"> &middot; <span class="font-mono" x-text="row.admission"></span></span>
                    </p>
                    <p class="mt-0.5 font-mono text-sm text-gray-700 dark:text-gray-200" x-text="row.phone"></p>

                    <form method="POST" :action="row.action" target="_blank" class="mt-4" x-on:submit="opened(current)">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="btn flex w-full items-center justify-center gap-2 rounded-[10px] bg-[#25D366] px-4 py-3 text-base font-bold text-white hover:bg-[#1ebe5b]">
                            <i class="fa-brands fa-whatsapp text-xl" aria-hidden="true"></i> Open WhatsApp
                        </button>
                    </form>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <small class="field-hint">WhatsApp opens with the message typed. Press send there, then come back here for the next parent.</small>
                        <button type="button" class="shrink-0 text-sm font-semibold text-gray-700 hover:underline dark:text-gray-200" x-on:click="skip()">Skip</button>
                    </div>
                </div>
            </template>

            <template x-if="! row">
                <div class="rounded-[10px] border border-green-200 bg-green-50 p-6 text-center dark:border-green-900/50 dark:bg-green-900/20">
                    <i class="fa-solid fa-circle-check text-3xl text-[#1ebe5b]" aria-hidden="true"></i>
                    <p class="mt-2 font-semibold text-gray-900 dark:text-white">All done</p>
                    <p class="text-sm text-gray-700 dark:text-gray-200">Every parent on this list has been sent their child's result details.</p>
                </div>
            </template>

            {{-- Everyone, ticked off as they go. Tap one to send it now. --}}
            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="(r, i) in rows" :key="r.id">
                        <li class="flex items-center gap-3 px-4 py-3" :class="i === current ? 'bg-green-50 dark:bg-green-900/20' : ''">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs"
                                  :class="r.done ? 'bg-[#25D366] text-white' : (r.problem ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-200')">
                                <i class="fa-solid" :class="r.done ? 'fa-check' : (r.problem ? 'fa-exclamation' : '')" aria-hidden="true"></i>
                                <span x-show="! r.done && ! r.problem" x-text="i + 1"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="r.parent"></span>
                                <span class="block truncate text-xs text-gray-700 dark:text-gray-300" x-text="r.student + ' · ' + r.phone"></span>
                                <span x-show="r.problem" class="block text-xs text-red-700 dark:text-red-400" x-text="r.problem"></span>
                            </span>
                            <span x-show="r.done" class="text-xs font-semibold text-green-700 dark:text-green-400">Sent</span>
                            <button type="button" x-show="sendable(r) && i !== current" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300" x-on:click="current = i; window.scrollTo({ top: 0, behavior: 'smooth' })">Send now</button>
                        </li>
                    </template>
                </ul>
            </div>
        @endif
    </div>
</x-dashboard-layout>
