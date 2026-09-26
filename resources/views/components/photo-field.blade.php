@props([
    'name' => 'photo',
    'id' => null,
    'label' => 'Passport Photograph',
    'existing' => null,
    'helper' => 'JPG, PNG or WebP, up to 10MB. Large phone photos are resized automatically.',

    // Kept in step with App\Support\Uploads\ImageProfile::Portrait.
    'maxKb' => \App\Support\Uploads\ImageProfile::Portrait->maxKilobytes(),
])

@php($fieldId = $id ?? $name)

{{--
    One photograph field, two ways to fill it.

    The real <input type="file"> is here and hidden. Both the camera and the
    file picker write into it, so what arrives at the server is an ordinary
    upload either way, same validation, same optimisation, same private disk.
    There is no second endpoint and no base64 payload.

    The photograph is bound to whichever record this form saves, so it cannot
    be attached to a different student or staff member: the input has no id in
    it for anybody to change.
--}}
<div
    x-data="photoField({ inputId: '{{ $fieldId }}', existing: @js($existing), maxKb: {{ (int) $maxKb }} })"
    x-on:beforeunload.window="destroy()"
    class="space-y-2"
>
    <label class="field-label" for="{{ $fieldId }}">{{ $label }}</label>

    <input
        id="{{ $fieldId }}"
        type="file"
        name="{{ $name }}"
        accept="image/jpeg,image/png,image/webp"
        class="sr-only"
        x-ref="file"
        x-on:change="chooseFile($event)"
    >

    {{-- The phone's own camera app, for when the in-page camera cannot run
         (an in-app browser, a refused permission). No name, so it is never
         submitted: what it captures is written into the input above. --}}
    <input
        type="file"
        accept="image/*"
        capture
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
        data-no-image-prep
        x-ref="capture"
        x-on:change="captureFromNativeCamera($event)"
    >

    {{-- What is currently attached, or a placeholder. --}}
    <div class="flex items-start gap-4">
        <span class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
            <template x-if="preview">
                <img :src="preview" alt="" class="h-full w-full object-cover">
            </template>
            <template x-if="! preview">
                <i class="fa-solid fa-user text-[26px] text-gray-300" aria-hidden="true"></i>
            </template>
        </span>

        <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    x-on:click="openCamera()"
                    x-bind:disabled="busy"
                    class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-[12.5px] font-bold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                >
                    <i class="fa-solid fa-camera text-[13px]" aria-hidden="true"></i>
                    <span x-text="busy ? 'Starting camera…' : 'Take Photo'"></span>
                </button>

                <button
                    type="button"
                    x-on:click="$refs.file.click()"
                    x-bind:disabled="preparing"
                    class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-[12.5px] font-bold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200"
                >
                    <i class="fa-solid fa-upload text-[13px]" aria-hidden="true"></i>
                    <span x-text="preparing ? 'Preparing photo…' : (preview ? 'Replace Photo' : 'Upload Photo')"></span>
                </button>

                <button
                    type="button"
                    x-show="preview"
                    x-cloak
                    x-on:click="clear()"
                    class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-[12.5px] font-bold text-red-600 transition hover:border-red-300 dark:border-gray-600"
                >
                    <i class="fa-solid fa-xmark text-[13px]" aria-hidden="true"></i>
                    Remove
                </button>
            </div>

            <small class="field-hint block">{{ $helper }}</small>

            <template x-if="error">
                <small class="block text-[11.5px] font-semibold text-red-700" x-text="error"></small>
            </template>

            <template x-if="offerNativeCamera">
                <button
                    type="button"
                    x-on:click="openNativeCamera()"
                    class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-[12.5px] font-bold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 dark:border-gray-600 dark:text-gray-200"
                >
                    <i class="fa-solid fa-mobile-screen text-[13px]" aria-hidden="true"></i>
                    Use Phone Camera
                </button>
            </template>

            @error($name)<small class="block text-[11.5px] font-semibold text-red-700">{{ $message }}</small>@enderror
        </div>
    </div>

    {{-- The camera, and the capture awaiting a decision. A dialog rather than
         an inline panel, because a passport photograph is worth seeing large
         enough to judge before it is kept. --}}
    <div
        x-show="mode === 'camera' || mode === 'review'"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        x-on:keydown.escape.window="cancelCamera()"
    >
        <div class="w-full max-w-sm overflow-hidden rounded-[12px] bg-white shadow-2xl dark:bg-gray-800">
            <div class="relative aspect-square w-full bg-black">
                {{-- Mirrored, so framing yourself behaves like a mirror. The
                     capture is mirrored to match, see photo-field.js. --}}
                <video
                    x-ref="video"
                    x-show="mode === 'camera'"
                    playsinline
                    muted
                    autoplay
                    class="h-full w-full object-cover"
                    style="transform: scaleX(-1);"
                ></video>

                <template x-if="mode === 'review' && pending">
                    <img :src="pending.url" alt="" class="h-full w-full object-cover">
                </template>
            </div>

            <div class="space-y-2 p-4">
                <template x-if="mode === 'camera'">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            x-on:click="capture()"
                            class="btn flex flex-1 items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-primary-700"
                        >
                            <i class="fa-solid fa-circle-dot text-[14px]" aria-hidden="true"></i>
                            Take Photo
                        </button>
                        <button
                            type="button"
                            x-on:click="cancelCamera()"
                            class="btn rounded-[8px] border border-gray-300 px-4 py-2.5 text-[13px] font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200"
                        >
                            Cancel
                        </button>
                    </div>
                </template>

                {{-- Nothing is attached until this is pressed. --}}
                <template x-if="mode === 'review'">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            x-on:click="confirm()"
                            class="btn flex flex-1 items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2.5 text-[13px] font-bold text-white hover:bg-primary-700"
                        >
                            <i class="fa-solid fa-check text-[13px]" aria-hidden="true"></i>
                            Use This Photo
                        </button>
                        <button
                            type="button"
                            x-on:click="retake()"
                            class="btn rounded-[8px] border border-gray-300 px-4 py-2.5 text-[13px] font-bold text-gray-700 dark:border-gray-600 dark:text-gray-200"
                        >
                            Retake
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
