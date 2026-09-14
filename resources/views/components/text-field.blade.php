{{-- The project's field, and the only one.

     The dimensions are not here: height, radius, border, typography, padding
     and the focus state all come from the base rules in app.css, which every
     field in the application shares. This component adds only what is specific
     to a labelled field, the label above it, room for an icon or a trailing
     button, and the message underneath.

     That split matters. A field written by hand on some future page gets the
     same look without using this component at all, and changing the house
     style means changing one stylesheet rather than every caller. --}}
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
    $bag = $errors->getBag($errorBag);
    $errorMessages = $bag->get($name);
    $hasError = count($errorMessages) > 0;

    /*
     * old() is keyed by field NAME and nothing else, so on a page carrying
     * SEVERAL forms with the same field names, Payment Settings renders one
     * per payment method, a failed save in one form repopulates every other
     * form with the values that were just rejected.
     *
     * Repopulating is only ever wanted in the form that actually failed, and
     * that form is the one whose error bag has something in it. A field in a
     * named bag with no errors shows the stored value, which is what it is
     * for.
     *
     * Fields in the default bag are unchanged: one form on a page is the
     * ordinary case, and there old() is exactly right.
     */
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
                'class' => 'peer '
                    . ($icon ? 'pl-9 ' : '')
                    . ($hasTrailing ? 'pr-9 ' : '')
                    . ($hasError ? 'field-invalid' : ''),
            ]) }}
        >

        @if ($icon)
            {{-- After the input in the markup, not before it: peer-* styling
                 only reaches a later sibling, and this icon picks up the
                 field's focus colour. Absolute positioning means the source
                 order is not the visual order. Its box is sized against
                 --field-height so it stays centred if that number changes. --}}
            <span
                class="pointer-events-none absolute left-0 top-0 flex w-9 items-center justify-center text-[#9AAAC4] transition-colors peer-focus:text-primary-500 dark:text-gray-500"
                style="height: var(--field-height);"
            >
                {{-- A Font Awesome class ("fa-briefcase") or an SVG path, whichever
                     the caller passes: the recruitment forms use Font Awesome. --}}
                @if (str_starts_with($icon, 'fa-'))
                    <i class="fa-solid {{ $icon }} text-[13px]" aria-hidden="true"></i>
                @else
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="{{ $icon }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                @endif
            </span>
        @endif

        @if ($hasTrailing)
            <div class="absolute right-0 top-0 flex items-center" style="height: var(--field-height);">
                {{ $trailing ?? '' }}
            </div>
        @endif
    </div>

    @if ($hasError)
        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium leading-[1.45] text-red-600 dark:text-red-400">
            <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.75" />
                <path d="M12 8v5M12 15.5h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
            </svg>
            {{ $errorMessages[0] }}
        </p>
    @elseif ($helper)
        {{-- <small>, not <p>: this is small print ABOUT the control above it,
             which is what the element means. It also makes every hint in the
             application the same tag, so the ones written by hand next to file
             pickers and textareas, controls this component cannot wrap, match
             the ones it renders. --}}
        <small class="field-hint mt-1">{{ $helper }}</small>
    @endif
</div>
