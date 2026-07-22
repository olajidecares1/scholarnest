@props([
    'id' => 'password',
    'name' => 'password',
    'label' => 'Password',
    'placeholder' => 'Enter your password',
    'autocomplete' => 'current-password',
])

<div x-data="{ show: false }">
    <x-input-label :for="$id" :value="$label" />
    <div class="relative mt-1">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-primary-500">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="5" y="10" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </span>
        <x-text-input
            :id="$id"
            :name="$name"
            type="password"
            x-bind:type="show ? 'text' : 'password'"
            class="pl-10 pr-10"
            required
            :autocomplete="$autocomplete"
            :placeholder="$placeholder"
        />
        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path x-show="!show" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                <circle x-show="!show" cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                <path x-show="show" d="M3 3l18 18M10.6 10.6a2.5 2.5 0 003.5 3.5M6.5 6.7C4 8.3 2 12 2 12s3.5 6 10 6c1.6 0 3-.35 4.2-.9M17.3 17.3C19.7 15.7 22 12 22 12s-1.3-2.3-3.5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
