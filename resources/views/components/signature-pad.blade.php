@props([
    'action',
    'destroyAction' => null,
    'extractAction' => null,
    'current' => null,
    'title' => 'Register Your Signature',
    'description' => null,
])

{{-- A signature pad: draw it here, it is saved against you, nobody else.

     Everything below is presentation. Ownership is decided on the server from
     the session, see App\Http\Controllers\Concerns\RegistersSignatures, and
     this form has no field naming a signer, because there is nothing here that
     could be trusted to.

     Drawn at the device's own pixel density and stored with a transparent
     background, so it prints as a signature on a letterhead rather than as a
     white sticker over one.

     Or, with an extract-action, uploaded as a photograph or scan of a
     signature on paper: the paper is removed on the server and the signature
     alone comes back as a preview, kept only when "Use This Signature" saves
     it through the same action as a drawn one. --}}
<div
    x-data="signaturePad({
        action: @js($action),
        destroyAction: @js($destroyAction),
        extractAction: @js($extractAction),
        current: @js($current),
    })"
    x-on:keydown.escape.window="closeAll()"
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
                <small class="field-hint">{{ $description }}</small>
            @endif

            @if ($extractAction)
                <small class="field-hint">
                    Prefer pen and paper? Sign on plain white paper, take a clear photo, and upload it. We remove the
                    background automatically and show you the result before anything is saved.
                </small>
            @endif

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    x-on:click="open()"
                    class="btn inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700"
                >
                    <i class="fa-solid fa-signature"></i>
                    <span x-text="current ? 'Redraw My Signature' : @js($title)"></span>
                </button>

                @if ($extractAction)
                    <button
                        type="button"
                        x-on:click="choosePhoto()"
                        class="btn inline-flex items-center gap-2 rounded-[8px] border border-primary-300 px-4 py-2 text-sm font-semibold text-primary-700 transition-colors duration-200 hover:bg-primary-50 dark:border-primary-700 dark:text-primary-300 dark:hover:bg-primary-900/30"
                    >
                        <i class="fa-solid fa-camera" aria-hidden="true"></i>
                        <span>Upload a Photo</span>
                    </button>

                    {{-- image/* rather than a list of extensions, so a phone
                         offers its camera as well as its gallery. The server
                         decides what is really an image. --}}
                    <input type="file" x-ref="photo" accept="image/*" class="hidden" x-on:change="photoChosen($event)">
                @endif

                @if ($destroyAction)
                    <button
                        type="button"
                        x-show="current"
                        x-on:click="withdraw()"
                        class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition-colors duration-200 hover:border-red-300 hover:text-red-600 dark:border-gray-600 dark:text-gray-300"
                    ><i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> Remove</button>
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
                    <small class="field-hint mt-0.5">Draw your signature here. Use your finger, a stylus, or your mouse.</small>
                </div>
                <button type="button" x-on:click="close()" class="icon-btn icon-btn--neutral shrink-0" data-tooltip="Close" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
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
                <small class="block text-[11px] font-medium text-gray-400" x-show="! hasDrawing">Draw your signature here</small>
                <small class="block text-[11px] font-medium text-gray-400" x-show="hasDrawing" x-cloak>Sign above the line</small>
            </div>

            <p x-show="modalError" x-cloak x-text="modalError" class="mt-3 text-xs font-semibold text-red-600"></p>

            <a
                x-show="signInUrl"
                x-cloak
                :href="signInUrl"
                class="btn mt-2 inline-flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600"
            >
                <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
                Sign in again
            </a>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                <button type="button" x-on:click="close()" class="inline-flex items-center gap-2 rounded-[8px] px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel</button>
                <button type="button" x-on:click="clear()" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300"><i class="fa-solid fa-eraser btn-icon" aria-hidden="true"></i> Clear</button>
                <button
                    type="button"
                    x-on:click="save()"
                    :disabled="! hasDrawing || saving"
                    class="btn rounded-[8px] bg-primary-600 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                ><i class="fa-solid btn-icon" :class="saving ? 'fa-spinner fa-spin' : 'fa-floppy-disk'" aria-hidden="true"></i> <span x-text="saving ? 'Saving…' : 'Save Signature'">Save Signature</span></button>
            </div>
        </div>
    </div>
    @if ($extractAction)
        {{-- The photo route: what was found, for the signer to accept or
             replace. Nothing is saved until "Use This Signature". --}}
        <div
            x-show="uploadShowing"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Upload a photo of your signature"
        >
            <div class="absolute inset-0 bg-gray-900/60" x-on:click="closeUpload()"></div>

            <div class="relative z-10 w-full max-w-lg rounded-[12px] bg-white p-5 shadow-xl dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">Upload a Photo of Your Signature</h3>
                        <small class="field-hint mt-0.5">The background is removed automatically. Check the result, then keep it.</small>
                    </div>
                    <button type="button" x-on:click="closeUpload()" class="icon-btn icon-btn--neutral shrink-0" data-tooltip="Close" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                {{-- Checked behind the preview so a transparent background is
                     visibly transparent, not mistaken for white paper. --}}
                <div
                    class="mt-4 flex min-h-[160px] items-center justify-center overflow-hidden rounded-[10px] border-2 border-dashed border-gray-300 p-3 dark:border-gray-600"
                    style="background-color: #fff; background-image: linear-gradient(45deg, #f3f4f6 25%, transparent 25%), linear-gradient(-45deg, #f3f4f6 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f3f4f6 75%), linear-gradient(-45deg, transparent 75%, #f3f4f6 75%); background-size: 16px 16px; background-position: 0 0, 0 8px, 8px -8px, -8px 0;"
                >
                    <template x-if="extracting">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-500">
                            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                            Finding your signature&hellip;
                        </span>
                    </template>
                    <template x-if="! extracting && preview">
                        <img :src="preview" alt="Your signature, with the background removed" class="max-h-40 w-auto max-w-full object-contain">
                    </template>
                    <template x-if="! extracting && ! preview">
                        <span class="px-4 text-center text-xs font-semibold text-gray-400">No signature yet</span>
                    </template>
                </div>

                <ul class="mt-3 list-disc space-y-0.5 pl-5 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                    <li>Sign in dark ink (black or blue) on plain white, unlined paper.</li>
                    <li>Photograph it in good light, without a shadow across it.</li>
                    <li>Hold the phone close so the signature fills most of the photo.</li>
                </ul>

                <p x-show="uploadError" x-cloak x-text="uploadError" class="mt-3 text-xs font-semibold text-red-600"></p>

                <a
                    x-show="signInUrl"
                    x-cloak
                    :href="signInUrl"
                    class="btn mt-2 inline-flex items-center gap-1.5 rounded-[8px] bg-primary-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600"
                >
                    <i class="fa-solid fa-right-to-bracket text-[11px]"></i>
                    Sign in again
                </a>

                <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                    <button type="button" x-on:click="closeUpload()" class="inline-flex items-center gap-2 rounded-[8px] px-4 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel</button>
                    <button type="button" x-on:click="choosePhoto()" :disabled="extracting || saving" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-300"><i class="fa-solid fa-camera btn-icon" aria-hidden="true"></i> Choose Another Photo</button>
                    <button
                        type="button"
                        x-on:click="usePreview()"
                        :disabled="! preview || extracting || saving"
                        class="btn rounded-[8px] bg-primary-600 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    ><i class="fa-solid btn-icon" :class="saving ? 'fa-spinner fa-spin' : 'fa-check'" aria-hidden="true"></i> <span x-text="saving ? 'Saving…' : 'Use This Signature'">Use This Signature</span></button>
                </div>
            </div>
        </div>
    @endif
</div>
