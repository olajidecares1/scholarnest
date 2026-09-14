{{-- The live state of a document that is being extracted.

     This replaces a `<meta http-equiv="refresh">`, which reloaded the whole
     page every five seconds: it threw away scroll position, re-ran every query
     behind the page, and still could not say anything more useful than that it
     was trying. Polling one small JSON endpoint instead lets the panel report
     what is actually happening, including the case this was written for, the
     queue is not running, so nothing is going to happen at all until someone
     starts it.

     No percentage appears here. By the time a document reaches this page the
     measurable part (sending the bytes) is over, and the extractor gives no
     signal of how far through the document it is. The bar sweeps rather than
     fills, for the same reason the upload form drops its percentage.

     TWO AUDIENCES. A stalled queue is one fact with two useful sentences: the
     AkademicNest Team can start a worker and should be told which command does it,
     while a teacher cannot and should never be shown a shell command. This
     panel used to print the command to both, so a teacher whose upload was
     waiting was handed `php artisan queue:work` and left to wonder what to do
     with it.

     QueueWorkerHealth::stalledMessage() already drew that distinction, and the
     JSON endpoint behind this panel already passed it (false for staff, true
     for the Super Admin). Only the rendered markup ignored it, which is why
     the polled message and the visible one disagreed.

     @param upload               The upload being watched.
     @param statusUrl            JSON endpoint returning its live state.
     @param retryUrl             Where to POST to queue the extraction again.
     @param canOperateTheServer  Whether this viewer can start a worker.
                                 Defaults to false: the safe answer is the one
                                 that shows no shell command, so a new caller
                                 that forgets to say cannot leak operator
                                 instructions to a teacher. --}}

@props([
    'upload',
    'statusUrl',
    'retryUrl',
    'stalled' => false,
    'canOperateTheServer' => false,
])

<div
    x-data="{
        status: @js($upload->status->value),
        label: @js($upload->status->label()),
        message: @js($upload->status->progressMessage()),
        inProgress: @js($upload->status->isInProgress()),
        stalled: @js($stalled),
        error: @js($upload->error_message),
        questionCount: 0,
        timer: null,
        failures: 0,

        init() {
            if (this.inProgress) {
                this.start();
            }
        },

        start() {
            this.timer = setInterval(() => this.check(), 2500);
        },

        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        async check() {
            try {
                const response = await fetch(@js($statusUrl), { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                const state = await response.json();
                this.failures = 0;
                this.status = state.status;
                this.label = state.label;
                this.message = state.message;
                this.stalled = state.stalled;
                this.error = state.error;
                this.questionCount = state.question_count;

                if (! state.in_progress) {
                    this.stop();

                    // The page around this panel was rendered for an upload
                    // that had not finished. Now that it has, the questions it
                    // should be showing only exist after a reload.
                    window.location.reload();
                }
            } catch {
                this.failures += 1;

                if (this.failures >= 5) {
                    this.stop();
                    this.message = 'Lost contact with the server. Reload the page to check on this document.';
                }
            }
        },
    }"
    x-init="init()"
    {{ $attributes }}
>
    {{-- Still working. --}}
    <template x-if="inProgress && ! stalled">
        <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20 lg:rounded-[10px]">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-gears text-blue-600 dark:text-blue-400"></i>
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-300" x-text="label"></p>
            </div>

            <div class="upload-progress-track mt-3">
                <div class="upload-progress-bar upload-progress-bar--indeterminate"></div>
            </div>

            <p class="mt-2 text-xs text-blue-700 dark:text-blue-400" x-text="message"></p>
            <p class="mt-1 text-xs text-blue-600/70 dark:text-blue-400/70">
                Large documents can take a minute or two. This page updates on its own, so you do not need to refresh it.
            </p>
        </div>
    </template>

    {{-- Queued, but nothing is running to pick it up. This is the state that
         used to look identical to "working", and is why documents appeared to
         upload and then hang forever. --}}
    <template x-if="stalled">
        <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-900/20 lg:rounded-[10px]">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-600 dark:text-amber-400"></i>
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-300">Extraction has not started</p>
            </div>

            <p class="mt-2 text-xs text-amber-800 dark:text-amber-400">
                The document uploaded successfully and is safely stored, but it has not been read yet.
                Nothing has been lost. Press Try again to read it now.
            </p>

            @if ($canOperateTheServer)
                <p class="mt-2 text-xs text-amber-800/80 dark:text-amber-400/80">
                    Whoever administers this server can start it with
                    <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-[11px] dark:bg-amber-900/40">php artisan queue:work</code>.
                    In development, <code class="rounded bg-amber-100 px-1 py-0.5 font-mono text-[11px] dark:bg-amber-900/40">composer run dev</code>
                    starts it alongside the web server.
                </p>
            @else
                {{-- What a teacher can actually do about it: nothing to the
                     server, but telling somebody is a real action, and knowing
                     the document is safe is the part that matters to them. --}}
                <p class="mt-2 text-xs text-amber-800/80 dark:text-amber-400/80">
                    You do not need to upload it again. If it stays this way, please let the AkademicNest Team know.
                </p>
            @endif

            <form method="POST" action="{{ $retryUrl }}" class="mt-3">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-[8px] border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-700 dark:bg-transparent dark:text-amber-300">
                    <i class="fa-solid fa-rotate-right text-[11px]"></i>
                    Try again
                </button>
            </form>
        </div>
    </template>

    {{-- Failed, with a way back that does not require finding the file again. --}}
    <template x-if="status === 'failed'">
        <div class="rounded-[5px] border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20 lg:rounded-[10px]">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-red-600 dark:text-red-400"></i>
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">Extraction failed</p>
            </div>

            <p class="mt-2 text-xs text-red-700 dark:text-red-400" x-text="error"></p>

            <form method="POST" action="{{ $retryUrl }}" class="mt-3">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-[8px] border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-700 dark:bg-transparent dark:text-red-400">
                    <i class="fa-solid fa-rotate-right text-[11px]"></i>
                    Try extracting again
                </button>
            </form>
        </div>
    </template>
</div>
