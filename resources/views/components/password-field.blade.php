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
            <button
                type="button"
                @click="show = !show"
                tabindex="-1"
                class="flex h-11 w-11 shrink-0 items-center justify-center text-gray-400 transition-colors duration-200 hover:text-gray-600 dark:hover:text-gray-300"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path x-show="!show" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                    <circle x-show="!show" cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                    <path x-show="show" d="M3 3l18 18M10.6 10.6a2.5 2.5 0 003.5 3.5M6.5 6.7C4 8.3 2 12 2 12s3.5 6 10 6c1.6 0 3-.35 4.2-.9M17.3 17.3C19.7 15.7 22 12 22 12s-1.3-2.3-3.5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </x-slot:trailing>
    </x-text-field>
</div>
