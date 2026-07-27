@props([
    'name',
    'label' => null,
    'type' => 'text',
    'icon' => null,
    'placeholder' => null,
    'helper' => null,
    'value' => null,
    'required' => false,
    'autofocus' => false,
    'autocomplete' => null,
    'disabled' => false,
    'min' => null,
    'max' => null,
    'step' => null,
    'errorBag' => 'default',
])

@php
    $id = $attributes->get('id') ?? $name;
    $errorMessages = $errors->getBag($errorBag)->get($name);
    $hasError = count($errorMessages) > 0;
    $resolvedValue = old($name, $value);
@endphp

<div>
    <div
        class="group relative rounded-[5px] border-2 bg-white transition-all duration-200 ease-out lg:rounded-[10px] dark:bg-gray-800
            {{ $hasError
                ? 'border-red-400 focus-within:border-red-500 focus-within:ring-4 focus-within:ring-red-500/10 dark:border-red-500'
                : 'border-gray-300 hover:border-gray-400 focus-within:border-primary-500 focus-within:ring-4 focus-within:ring-primary-500/10 dark:border-gray-600 dark:hover:border-gray-500' }}"
    >
        @if ($label)
            <span
                class="pointer-events-none absolute -top-2.5 left-3 z-10 flex items-center gap-1 bg-white px-1.5 text-xs font-semibold uppercase tracking-wide transition-colors duration-200 dark:bg-gray-800
                    {{ $hasError
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-500 group-focus-within:text-primary-600 dark:text-gray-400 dark:group-focus-within:text-primary-400' }}"
            >
                <span class="h-2 w-px bg-current opacity-40"></span>
                {{ $label }}{{ $required ? ' *' : '' }}
                <span class="h-2 w-px bg-current opacity-40"></span>
            </span>
        @endif

        <div class="flex items-center">
            @if ($icon)
                <span
                    class="pointer-events-none flex h-11 w-11 shrink-0 items-center justify-center transition-colors duration-200
                        {{ $hasError ? 'text-red-500' : 'text-gray-400 group-focus-within:text-primary-500' }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="{{ $icon }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
            @endif

            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if ($resolvedValue !== null) value="{{ $resolvedValue }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($required) required @endif
                @if ($autofocus) autofocus @endif
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                @if ($disabled) disabled @endif
                @if ($min !== null) min="{{ $min }}" @endif
                @if ($max !== null) max="{{ $max }}" @endif
                @if ($step !== null) step="{{ $step }}" @endif
                {{ $attributes->except('id')->merge([
                    'class' => 'peer w-full min-w-0 border-0 bg-transparent py-3 text-base text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50 dark:text-white dark:placeholder:text-gray-500 '
                        . ($icon ? 'pl-0 pr-4' : 'px-4'),
                ]) }}
            >

            {{ $trailing ?? '' }}
        </div>
    </div>

    @if ($hasError)
        <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.75" />
                <path d="M12 8v5M12 15.5h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
            </svg>
            {{ $errorMessages[0] }}
        </p>
    @elseif ($helper)
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $helper }}</p>
    @endif
</div>
