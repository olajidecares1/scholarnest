@props([
    'id' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'placeholder' => 'Enter your email address',
    'autocomplete' => 'username',
    'autofocus' => false,
    'value' => null,
])

<div>
    <x-input-label :for="$id" :value="$label" />
    <div class="relative mt-1">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-primary-500">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                <path d="M3 6l9 7 9-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <x-text-input
            :id="$id"
            :name="$name"
            type="email"
            class="pl-10"
            :value="$value ?? old($name)"
            required
            :autofocus="$autofocus"
            :autocomplete="$autocomplete"
            :placeholder="$placeholder"
        />
    </div>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
