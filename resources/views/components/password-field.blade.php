@props([
    'name',
    'label' => null,
    'icon' => 'M5 10.5a2 2 0 012-2h10a2 2 0 012 2v7a2 2 0 01-2 2H7a2 2 0 01-2-2v-7z M8 10.5V7a4 4 0 018 0v3.5',
    'placeholder' => null,
    'helper' => null,
    'required' => false,
    'autocomplete' => 'current-password',
])

<div x-data="{ show: false }">
    <x-text-field
        :name="$name"
        :label="$label"
        type="password"
        x-bind:type="show ? 'text' : 'password'"
        :icon="$icon"
        :placeholder="$placeholder"
        :helper="$helper"
        :required="$required"
        :autocomplete="$autocomplete"
        :has-trailing="true"
        {{ $attributes }}
    >
        <x-slot:trailing>
            {{-- Font Awesome, which the app already loads, rather than a
                 hand-drawn SVG. Changing it here changes every password field
                 in the application, portal sign-ins, settings, staff and
                 student password forms, since they all come through this
                 component. Sized against the field so it never outgrows it. --}}
            <button
                type="button"
                @click="show = !show"
                tabindex="-1"
                :aria-label="show ? 'Hide password' : 'Show password'"
                class="flex w-9 shrink-0 items-center justify-center text-[#9AAAC4] transition-colors hover:text-[#5B7099] dark:text-gray-500 dark:hover:text-gray-300"
                style="height: var(--field-height);"
            >
                <i class="fa-solid text-[12px]" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        </x-slot:trailing>
    </x-text-field>
</div>
