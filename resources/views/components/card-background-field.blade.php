@props([
    'action',
    'current' => null,
    'title' => 'Card background image',
    'description' => null,
])

{{-- Manage the background image behind one card on the public website.

     One component, used by the News & Events card and by Academic Excellence,
     so the two controls cannot drift apart in wording or behaviour.

     The preview is of the file CHOSEN, not the file saved - read straight off
     the input with a FileReader before anything is uploaded, so an
     administrator sees what they picked before committing to it. Choosing a
     new file and thinking better of it costs nothing. --}}
<div
    class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
    x-data="{
        preview: null,
        tooBig: false,
        choose(event) {
            const file = event.target.files[0];
            this.preview = null;
            this.tooBig = false;

            if (! file) {
                return;
            }

            // Said before they upload it. The server refuses an oversized file
            // anyway, but finding that out after a slow upload is a reason to
            // give up on the whole thing.
            this.tooBig = file.size > 5 * 1024 * 1024;

            if (this.tooBig) {
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => (this.preview = e.target.result);
            reader.readAsDataURL(file);
        },
    }"
>
    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $title }}</h3>

    @if ($description)
        <p class="field-hint mt-1">{{ $description }}</p>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-4">
        @csrf

        {{-- What is showing on the website now, or what has just been chosen
             to replace it. --}}
        <div class="overflow-hidden rounded-[8px] border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
            <template x-if="preview">
                <img x-bind:src="preview" alt="" class="w-full object-cover" style="height: 160px;">
            </template>

            <template x-if="! preview">
                <div>
                    @if ($current)
                        <img src="{{ $current }}" alt="" class="w-full object-cover" style="height: 160px;">
                    @else
                        <div class="flex flex-col items-center justify-center gap-2 text-gray-500 dark:text-gray-400" style="height: 160px;">
                            <i class="fa-regular fa-image text-[26px]" aria-hidden="true"></i>
                            <p class="text-[12px] font-semibold">No background set. The card uses its plain background.</p>
                        </div>
                    @endif
                </div>
            </template>
        </div>

        <p x-show="preview" x-cloak class="field-hint mt-2">
            This is the image you have chosen. It is not saved until you press Save.
        </p>

        <div class="mt-3">
            <label for="bg-{{ Str::slug($title) }}" class="field-label">Choose an image</label>
            <input
                id="bg-{{ Str::slug($title) }}"
                type="file"
                name="background_image"
                accept="image/jpeg,image/png,image/webp"
                x-on:change="choose($event)"
                class="mt-1.5 w-full"
            >
            <p class="field-hint mt-1">JPEG, PNG or WebP, up to 5MB, at least 1200 &times; 500 pixels &mdash; wider is better. It is stretched across the full width of the page, so a small image will look soft.</p>

            <p x-show="tooBig" x-cloak class="mt-1 text-[11.5px] font-semibold text-red-700">
                That image is larger than 5MB. Please choose a smaller one.
            </p>

            @error('background_image')<p class="mt-1 text-[11.5px] font-semibold text-red-700">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
                type="submit"
                x-bind:disabled="tooBig"
                class="rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ $current ? 'Replace image' : 'Save image' }}
            </button>

            @if ($current)
                {{-- Its own submit rather than a second form, because a form
                     inside a form is not valid HTML and browsers quietly drop
                     the inner one. --}}
                <button
                    type="submit"
                    name="remove"
                    value="1"
                    class="rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-900 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
                >
                    Remove image
                </button>
            @endif
        </div>
    </form>
</div>
