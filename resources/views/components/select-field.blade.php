{{-- A select, styled as a field like every other field.

     This used to be a bespoke drum-roll picker: a button 52px tall with the
     label inside it and a scrolling wheel in a popover. It behaved unlike
     every other control in the application and could not be made to match the
     registration page, because it was not a field, it was a button pretending
     to be one.

     It is a native <select> now. The base rules in app.css give it the same
     height, border, radius, typography and focus as every input, and draw its
     chevron, so it matches by construction rather than by imitation. Native
     also means it opens the platform's own picker on a phone, which is the
     control people already know.

     The x-modelable wrapper is kept because callers bind to it, so every
     existing `model="..."` continues to work unchanged. --}}
@props([
    'name',
    'label' => null,
    'icon' => null,
    'helper' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option',
    'required' => false,
    'errorBag' => 'default',
    'model' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $errorMessages = $errors->getBag($errorBag)->get($name);
    $hasError = count($errorMessages) > 0;
    $resolvedValue = (string) old($name, $selected);
    $optionList = collect($options)
        ->map(fn ($optLabel, $optValue) => ['value' => (string) $optValue, 'label' => $optLabel])
        ->values();

    // Only when the caller has not already supplied an empty option of their
    // own, a filter whose first entry is "All Classes" does not need a second
    // blank row above it saying "Select an option".
    $needsPlaceholderOption = $optionList->doesntContain(fn (array $option) => $option['value'] === '');
@endphp

<div
    x-modelable="value"
    @if ($model) x-model="{{ $model }}" @endif
    x-data="{ value: @js($resolvedValue) }"
>
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif

    <div class="relative {{ $label ? 'mt-1' : '' }}">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            x-model="value"
            @if ($required) required @endif
            {{ $attributes->except('id')->merge([
                'class' => 'peer '
                    . ($icon ? 'pl-9 ' : '')
                    . ($hasError ? 'field-invalid' : ''),
            ]) }}
        >
            @if ($needsPlaceholderOption)
                <option value="" @if ($required) disabled @endif>{{ $placeholder }}</option>
            @endif

            @foreach ($optionList as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>

        @if ($icon)
            <span
                class="pointer-events-none absolute left-0 top-0 flex w-9 items-center justify-center text-[#9AAAC4] transition-colors peer-focus:text-primary-500 dark:text-gray-500"
                style="height: var(--field-height);"
            >
                {{-- A Font Awesome class ("fa-briefcase") or an SVG path, whichever
                     the caller passes: the recruitment forms use Font Awesome. --}}
                <i class="fa-solid {{ \App\Support\FieldIcon::fa($icon) }} text-[13px]" aria-hidden="true"></i>
            </span>
        @endif
    </div>

    @if ($hasError)
        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium leading-[1.45] text-red-600 dark:text-red-400">
            <i class="fa-solid fa-circle-exclamation shrink-0 text-[10px] leading-none" aria-hidden="true"></i>
            {{ $errorMessages[0] }}
        </p>
    @elseif ($helper)
        <small class="field-hint mt-1">{{ $helper }}</small>
    @endif
</div>
