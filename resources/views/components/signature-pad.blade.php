@props([
    'action',
    'destroyAction' => null,
    'current' => null,
    'title' => 'Register Your Signature',
    'description' => null,
])

{{-- A signature pad: draw it here, it is saved against you, nobody else.

     Everything below is presentation. Ownership is decided on the server from
     the session - see App\Http\Controllers\Concerns\RegistersSignatures - and
     this form has no field naming a signer, because there is nothing here that
     could be trusted to.

     Drawn at the device's own pixel density and stored with a transparent
     background, so it prints as a signature on a letterhead rather than as a
     white sticker over one. --}}
<div
    x-data="signaturePad({
        action: @js($action),
        destroyAction: @js($destroyAction),
        current: @js($current),
    })"
    x-on:keydown.escape.window="close()"
    class="space-y-3"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="shrink-0">
            <template x-if="current">
                <img :src="current" alt="Your registered signature" class="h-16 w-full rounded-[8px] border border-gray-200 bg-white object-contain p-1 dark:border-gray-700 sm:w-40" style="height: 64px;">
            </template>
            <template x-if="! current">
                <span class="flex h-16 w-full items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400 dark:border-gray-600 dark:text-gray-500 sm:w-40" style="height: 64px;">Not registered</span>
            </template>
        </div>

        <div class="min-w-0 flex-1">
            @if ($description)
                <p class="field-hint">{{ $description }}</p>
            @endif

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    x-on:click="open()"
                    class="inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700"
                >
                    <i class="fa-solid fa-signature"></i>
                    <span x-text="current ? 'Redraw My Signature' : @js($title)"></span>
                </button>

                @if ($destroyAction)
                    <button
                        type="button"
                        x-show="current"
                        x-on:click="withdraw()"
                        class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:border-red-300 hover:text-red-600 dark:border-gray-600 dark:text-gray-300"
                    >Remove</button>
                @endif
            </div>

            <p x-show="message" x-cloak x-text="message" class="mt-2 text-xs font-semibold" :class="failed ? 'text-red-600' : 'text-green-600'"></p>
        </div>
    </div>

    {{-- The modal. Fixed and scroll-locked, because a page that scrolls under
         a finger drawing on it is unusable on a phone. --}}
    <div
        x-show="showing"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Draw your signature"
    >
        <div class="absolute inset-0 bg-gray-900/60" x-on:click="close()"></div>

        <div class="relative z-10 w-full max-w-lg rounded-[12px] bg-white p-5 shadow-xl dark:bg-gray-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $title }}</h3>
                    <p class="field-hint mt-0.5">Draw your signature here. Use your finger, a stylus, or your mouse.</p>
                </div>
                <button type="button" x-on:click="close()" aria-label="Close" class="rounded-[6px] px-2 py-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            {{-- touch-action:none is what stops the page panning under a
                 finger that is trying to write on it. --}}
            <div class="mt-4 overflow-hidden rounded-[10px] border-2 border-dashed border-gray-300 bg-white dark:border-gray-600">
                <canvas
                    x-ref="canvas"
                    class="block w-full cursor-crosshair"
                    style="touch-action: none; height: 200px;"
                ></canvas>
            </div>

            <div class="mt-1 flex items-center justify-between">
                <p class="text-[11px] font-medium text-gray-400" x-show="! hasDrawing">Draw your signature here</p>
                <p class="text-[11px] font-medium text-gray-400" x-show="hasDrawing" x-cloak>Sign above the line</p>
            </div>

            <p x-show="modalError" x-cloak x-text="modalError" class="mt-3 text-xs font-semibold text-red-600"></p>

            <a
                x-show="signInUrl"
                x-cloak
                :href="signInUrl"
                class="mt-2 inline-flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600"
            >
                <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
                Sign in again
            </a>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button type="button" x-on:click="close()" class="rounded-[8px] px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400">Cancel</button>
                <button type="button" x-on:click="clear()" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300">Clear</button>
                <button
                    type="button"
                    x-on:click="save()"
                    :disabled="! hasDrawing || saving"
                    class="rounded-[8px] bg-primary-600 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="saving ? 'Saving…' : 'Save Signature'"
                ></button>
            </div>
        </div>
    </div>
</div>
