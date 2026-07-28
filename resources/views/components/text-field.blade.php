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
    'hasTrailing' => false,
])

@php
    $id = $attributes->get('id') ?? $name;
    $errorMessages = $errors->getBag($errorBag)->get($name);
    $hasError = count($errorMessages) > 0;
    $resolvedValue = old($name, $value);
    $labelLeft = $icon ? 'left-9' : 'left-3';
@endphp

<div>
    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($resolvedValue !== null) value="{{ $resolvedValue }}" @endif
            placeholder=" "
            @if ($required) required @endif
            @if ($autofocus) autofocus @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($disabled) disabled @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($step !== null) step="{{ $step }}" @endif
            {{ $attributes->except('id')->merge([
                'class' => 'peer block h-11 w-full min-w-0 rounded-[2px] border bg-white text-[13.5px] font-medium text-gray-900 placeholder-transparent shadow-sm transition-all duration-200 ease-out focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:opacity-60 dark:bg-gray-800 dark:text-white '
                    . ($icon ? 'pl-9 ' : 'pl-3 ')
                    . ($hasTrailing ? 'pr-9 ' : 'pr-3 ')
                    . ($hasError
                        ? 'border-red-400 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 dark:border-red-500'
                        : 'border-gray-300 hover:border-gray-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 dark:border-gray-600 dark:hover:border-gray-500'),
            ]) }}
        >

        @if ($icon)
            <span
                class="pointer-events-none absolute left-0 top-0 flex h-11 w-9 items-center justify-center transition-colors duration-200
                    {{ $hasError ? 'text-red-500' : 'text-gray-400 peer-focus:text-primary-500' }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="{{ $icon }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        @endif

        <label
            for="{{ $id }}"
            class="pointer-events-none absolute {{ $labelLeft }} top-1/2 -translate-y-1/2 rounded bg-transparent px-0 text-[13.5px] text-gray-400 transition-all duration-200 ease-out dark:text-gray-500
                peer-[:not(:placeholder-shown)]:top-0 peer-[:not(:placeholder-shown)]:-translate-y-1/2 peer-[:not(:placeholder-shown)]:bg-white peer-[:not(:placeholder-shown)]:px-1 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:font-semibold peer-[:not(:placeholder-shown)]:text-gray-500 dark:peer-[:not(:placeholder-shown)]:bg-gray-800 dark:peer-[:not(:placeholder-shown)]:text-gray-400
                peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:bg-white peer-focus:px-1 peer-focus:text-[11px] peer-focus:font-semibold dark:peer-focus:bg-gray-800 {{ $hasError ? 'peer-focus:text-red-500' : 'peer-focus:text-primary-600 dark:peer-focus:text-primary-400' }}"
        >
            {{ $label }}{{ $required ? ' *' : '' }}
        </label>

        @if ($hasTrailing)
            <div class="absolute right-0 top-0 flex h-11 items-center">
                {{ $trailing ?? '' }}
            </div>
        @endif
    </div>

    @if ($hasError)
        <p class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.75" />
                <path d="M12 8v5M12 15.5h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
            </svg>
            {{ $errorMessages[0] }}
        </p>
    @elseif ($helper)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $helper }}</p>
    @endif
</div>
