@props([
    'name',
    'label' => null,
    'icon' => null,
    'placeholder' => null,
    'helper' => null,
    'value' => null,
    'required' => false,
    'rows' => 4,
    'errorBag' => 'default',
])

@php
    $id = $attributes->get('id') ?? $name;
    $errorMessages = $errors->getBag($errorBag)->get($name);
    $hasError = count($errorMessages) > 0;
    $resolvedValue = old($name, $value);
    $labelLeft = $icon ? 'left-11' : 'left-4';
@endphp

<div>
    <div
        class="relative rounded-[8px] border bg-white shadow-sm transition-all duration-200 ease-out lg:rounded-[10px] dark:bg-gray-800
            {{ $hasError
                ? 'border-red-400 has-[:focus]:border-red-500 has-[:focus]:shadow-[0_0_0_4px_rgba(239,68,68,0.1)] dark:border-red-500'
                : 'border-gray-200 hover:border-gray-300 has-[:focus]:border-primary-500 has-[:focus]:shadow-[0_0_0_4px_rgba(24,119,242,0.1)] dark:border-gray-700 dark:hover:border-gray-600' }}"
    >
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder=" "
            @if ($required) required @endif
            {{ $attributes->except('id')->merge([
                'class' => 'peer block w-full min-w-0 resize-y border-0 bg-transparent pb-2.5 pt-6 text-[15px] font-medium text-gray-900 placeholder-transparent focus:outline-none focus:ring-0 dark:text-white '
                    . ($icon ? 'pl-11 pr-4' : 'px-4'),
            ]) }}
        >{{ $resolvedValue }}</textarea>

        @if ($icon)
            <span
                class="pointer-events-none absolute left-0 top-0 flex h-[52px] w-11 items-center justify-center transition-colors duration-200
                    {{ $hasError ? 'text-red-500' : 'text-gray-400 peer-focus:text-primary-500' }}"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="{{ $icon }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        @endif

        <label
            for="{{ $id }}"
            class="pointer-events-none absolute {{ $labelLeft }} top-5 text-[15px] text-gray-400 transition-all duration-200 ease-out dark:text-gray-500
                peer-[:not(:placeholder-shown)]:top-3.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:font-bold peer-[:not(:placeholder-shown)]:uppercase peer-[:not(:placeholder-shown)]:tracking-wide peer-[:not(:placeholder-shown)]:text-gray-500 dark:peer-[:not(:placeholder-shown)]:text-gray-400
                peer-focus:top-3.5 peer-focus:text-[11px] peer-focus:font-bold peer-focus:uppercase peer-focus:tracking-wide {{ $hasError ? 'peer-focus:text-red-500' : 'peer-focus:text-primary-600 dark:peer-focus:text-primary-400' }}"
        >
            {{ $label }}{{ $required ? ' *' : '' }}
        </label>
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
