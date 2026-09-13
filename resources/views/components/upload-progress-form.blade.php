{{-- A file upload that tells the truth about what it is doing.

     A CBT document goes through two phases that feel like one to the person
     waiting. The first - sending the bytes - is measurable, and we report it
     as a real percentage taken from the browser's own upload events. The
     second - extracting questions from the document - is not measurable: the
     extractor does not report how far through a paper it is, and a 20MB scan
     can take a minute.

     So the bar deliberately stops being a percentage at that point. Running a
     number up to 100% while extraction is still going would be a lie that
     makes the wait feel broken rather than long, which is the failure this
     component exists to fix. Instead the bar switches to an indeterminate
     animation and the caption says what is actually happening.

     @param action   Where to POST the form.
     @param maxMb    The size limit, quoted so the form and the validator agree.
     @param label    The submit button's wording. --}}

@props([
    'action',
    'maxMb' => 21,
    'label' => 'Upload & Extract',
])

<form
    method="POST"
    action="{{ $action }}"
    enctype="multipart/form-data"
    x-data="uploadProgressForm({ maxMb: {{ $maxMb }} })"
    x-on:submit.prevent="send($event.target)"
    {{ $attributes->merge(['class' => 'mt-4 space-y-2']) }}
>
    @csrf

    {{-- The fields stay untouched while idle and are hidden once the upload is
         under way, so nothing can be edited in a way the request would not
         reflect. --}}
    <div x-show="phase === 'idle'" x-cloak>
        {{ $slot }}

        <button
            type="submit"
            class="mt-2 flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md"
        >
            <i class="fa-solid fa-arrow-up-from-bracket text-[13px]"></i>
            {{ $label }}
        </button>
    </div>

    {{-- Everything from here down is the progress interface. --}}
    <div x-show="phase !== 'idle'" x-cloak class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <p class="flex min-w-0 items-center gap-2 text-[13px] font-semibold text-gray-900 dark:text-white">
                <i class="fa-solid text-[12px]" :class="icon"></i>
                <span class="truncate" x-text="heading"></span>
            </p>

            {{-- A percentage appears only while one is genuinely known. --}}
            <span
                x-show="percent !== null"
                class="shrink-0 text-[13px] font-bold tabular-nums text-primary-600 dark:text-primary-400"
                x-text="percent + '%'"
            ></span>
        </div>

        <div class="upload-progress-track" :class="phase === 'failed' ? 'upload-progress-track--failed' : ''">
            <div
                class="upload-progress-bar"
                :class="percent === null ? 'upload-progress-bar--indeterminate' : ''"
                :style="percent === null ? '' : `width: ${percent}%`"
            ></div>
        </div>

        <p class="field-hint" x-text="message"></p>

        {{-- Bytes sent, so a slow connection looks slow rather than stuck. --}}
        <p x-show="phase === 'uploading' && totalBytes > 0" class="field-hint tabular-nums" x-cloak>
            <span x-text="formatBytes(sentBytes)"></span> of <span x-text="formatBytes(totalBytes)"></span>
        </p>

        <div x-show="phase === 'failed'" x-cloak class="flex flex-wrap items-center gap-2">
            {{-- Signed out: signing in is the fix, not trying again. --}}
            <a
                x-show="signInUrl"
                :href="signInUrl"
                class="flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-600"
            >
                <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
                Sign in again
            </a>

            <button
                type="button"
                x-show="needsReload"
                x-on:click="window.location.reload()"
                class="flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-600"
            >
                <i class="fa-solid fa-rotate-right text-[11px]"></i>
                Reload page
            </button>

            <button
                type="button"
                x-show="! signInUrl && ! needsReload"
                x-on:click="reset()"
                class="flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-rotate-left text-[11px]"></i>
                Try again
            </button>
        </div>

        <div x-show="phase === 'done'" x-cloak>
            <a
                :href="redirectUrl"
                class="inline-flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-600"
            >
                <i class="fa-solid fa-arrow-right text-[11px]"></i>
                View the extracted questions
            </a>
        </div>
    </div>
</form>
