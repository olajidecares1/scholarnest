{{-- The multi-line counterpart of x-text-field, and deliberately the same
     thing: same label, same border, same typography, same focus, same hint.
     Only the height differs, because it has to. Everything visual comes from
     the shared base rules in app.css. --}}
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
    $bag = $errors->getBag($errorBag);
    $errorMessages = $bag->get($name);
    $hasError = count($errorMessages) > 0;

    // Only the form that failed repopulates from old input. See the note in
    // x-text-field, which does the same thing for the same reason.
    $repopulate = $errorBag === 'default' || $bag->isNotEmpty();

    $resolvedValue = $repopulate ? old($name, $value) : $value;
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="field-label">
            {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
        </label>
    @endif

    <div class="relative {{ $label ? 'mt-1' : '' }}">
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            {{ $attributes->except('id')->merge([
                'class' => 'peer '
                    . ($icon ? 'pl-9 ' : '')
                    . ($hasError ? 'field-invalid' : ''),
            ]) }}
        >{{ $resolvedValue }}</textarea>

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
