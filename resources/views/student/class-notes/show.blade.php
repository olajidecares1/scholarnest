{{-- One class note: read it here, copy it, or download the Word file.

     The text was pulled out of the document when the teacher uploaded it, so
     a pupil with no copy of Word on a shared phone can still read the note and
     copy it into their own work, which is the point of the feature. A legacy
     .doc cannot be read that way, and the panel says so rather than sitting
     empty. --}}
<x-student-layout page-title="Class Note" :page-subtitle="$note->title">
    <div class="space-y-5" x-data="{
        copied: false,
        async copy() {
            const text = $refs.body?.innerText ?? ''

            try {
                await navigator.clipboard.writeText(text)
            } catch (e) {
                // Clipboard access needs a secure context and a permission.
                // Falling back to selecting the text lets the pupil copy it
                // with their own keyboard or long-press instead of being told
                // nothing happened.
                const range = document.createRange()
                range.selectNodeContents($refs.body)
                const selection = window.getSelection()
                selection.removeAllRanges()
                selection.addRange(range)

                return
            }

            this.copied = true
            setTimeout(() => { this.copied = false }, 2000)
        },
    }">
        <a href="{{ route('student.class-notes.index', $school) }}" class="inline-flex items-center gap-1.5 text-[12px] font-bold text-gray-500 transition hover:text-primary-600 dark:text-gray-400">
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            All class notes
        </a>

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                    <i class="fa-solid fa-file-word text-lg"></i>
                </span>

                <div class="min-w-0 flex-1">
                    <h1 class="text-base font-bold text-gray-900 dark:text-white">{{ $note->title }}</h1>
                    <small class="block mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                        @if ($note->subject){{ $note->subject }} &middot; @endif
                        @if ($note->staff){{ $note->staff->fullName() }} &middot; @endif
                        {{ $note->created_at->format('j M Y') }}
                    </small>
                </div>
            </div>

            @if ($note->description)
                <div class="mt-4 rounded-[8px] bg-gray-50 p-4 dark:bg-gray-900/40">
                    <p class="text-[12.5px] leading-[1.65] text-gray-700 dark:text-gray-300">{{ $note->description }}</p>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <a href="{{ route('student.class-notes.download', [$school, $note]) }}" class="btn inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-700">
                    <i class="fa-solid fa-download text-[12px]"></i>
                    Download ({{ $note->readableSize() }})
                </a>

                @if ($note->isReadable())
                    <button type="button" x-on:click="copy()" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2.5 text-[13px] font-bold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 dark:border-gray-600 dark:text-gray-200">
                        <i class="fa-solid text-[12px]" x-bind:class="copied ? 'fa-check' : 'fa-copy'"></i>
                        <span x-text="copied ? 'Copied' : 'Copy note'"></span>
                    </button>
                @endif
            </div>
        </div>

        @if ($note->isReadable())
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <i class="fa-solid fa-book-open text-primary-500"></i>
                    The note
                </h2>
                <small class="field-hint block">You can select this text and copy it for your own work.</small>

                {{-- select-text and whitespace-pre-line: the document's own
                     line breaks are all the structure plain text has, and
                     collapsing them would run every paragraph together. --}}
                <div x-ref="body" class="mt-4 select-text whitespace-pre-line break-words text-[13.5px] leading-[1.75] text-gray-800 dark:text-gray-200">{{ $note->body_text }}</div>
            </div>
        @else
            <div class="flex items-start gap-3 rounded-[10px] border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <i class="fa-solid fa-circle-info mt-0.5 text-gray-400"></i>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">This note can only be downloaded</h2>
                    <small class="block mt-1 text-[12.5px] leading-[1.6] text-gray-600 dark:text-gray-300">
                        Its text could not be shown here, so download the document above and open it in Microsoft
                        Word, Google Docs or any app that reads Word files.
                    </small>
                </div>
            </div>
        @endif
    </div>
</x-student-layout>
