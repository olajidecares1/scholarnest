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
])

@php
    $id = $attributes->get('id') ?? $name;
    $errorMessages = $errors->getBag($errorBag)->get($name);
    $hasError = count($errorMessages) > 0;
    $resolvedValue = old($name, $selected);
    $optionList = collect($options)->map(fn ($optLabel, $optValue) => ['value' => (string) $optValue, 'label' => $optLabel])->values();
@endphp

<div
    x-data="{
        open: false,
        value: @js((string) $resolvedValue),
        options: @js($optionList),
        rowHeight: 44,
        highlightedIndex: 0,
        scrollTimer: null,
        get selectedLabel() {
            const match = this.options.find(o => o.value === this.value);
            return match ? match.label : @js($placeholder);
        },
        init() {
            const idx = this.options.findIndex(o => o.value === this.value);
            this.highlightedIndex = idx >= 0 ? idx : 0;
        },
        openPicker() {
            if (this.options.length === 0) return;
            this.open = true;
            this.$nextTick(() => this.scrollToIndex(this.highlightedIndex, false));
        },
        closePicker() {
            this.open = false;
            this.$refs.trigger?.focus();
        },
        scrollToIndex(i, smooth = true) {
            const el = this.$refs.wheel;
            if (!el) return;
            el.scrollTo({ top: i * this.rowHeight, behavior: smooth ? 'smooth' : 'instant' });
        },
        selectIndex(i, close = false) {
            if (i < 0 || i >= this.options.length) return;
            this.highlightedIndex = i;
            this.value = this.options[i].value;
            this.scrollToIndex(i);
            if (close) this.closePicker();
        },
        onWheelScroll() {
            clearTimeout(this.scrollTimer);
            this.scrollTimer = setTimeout(() => {
                const el = this.$refs.wheel;
                if (!el) return;
                const idx = Math.round(el.scrollTop / this.rowHeight);
                const clamped = Math.max(0, Math.min(this.options.length - 1, idx));
                this.highlightedIndex = clamped;
                this.value = this.options[clamped]?.value ?? this.value;
                this.scrollToIndex(clamped);
            }, 90);
        },
        onTriggerKeydown(e) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
                e.preventDefault();
                this.openPicker();
            }
        },
        onWheelKeydown(e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); this.selectIndex(this.highlightedIndex + 1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.selectIndex(this.highlightedIndex - 1); }
            if (e.key === 'Enter') { e.preventDefault(); this.selectIndex(this.highlightedIndex, true); }
            if (e.key === 'Escape') { e.preventDefault(); this.closePicker(); }
        },
    }"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" :value="value" @if ($required) required @endif>

    <button
        type="button"
        x-ref="trigger"
        @click="openPicker()"
        @keydown="onTriggerKeydown($event)"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open.toString()"
        class="group relative flex w-full items-center rounded-[5px] border-2 bg-white text-left transition-all duration-200 ease-out focus:outline-none lg:rounded-[10px] dark:bg-gray-800
            {{ $hasError
                ? 'border-red-400 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 dark:border-red-500'
                : 'border-gray-300 hover:border-gray-400 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 dark:border-gray-600 dark:hover:border-gray-500' }}"
    >
        @if ($label)
            <span
                class="pointer-events-none absolute -top-2.5 left-3 z-10 flex items-center gap-1 bg-white px-1.5 text-xs font-semibold uppercase tracking-wide transition-colors duration-200 dark:bg-gray-800
                    {{ $hasError
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-500 group-focus:text-primary-600 dark:text-gray-400' }}"
            >
                <span class="h-2 w-px bg-current opacity-40"></span>
                {{ $label }}{{ $required ? ' *' : '' }}
                <span class="h-2 w-px bg-current opacity-40"></span>
            </span>
        @endif

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center text-gray-400 group-focus:text-primary-500">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="{{ $icon }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        @endif

        <span
            class="min-w-0 flex-1 truncate py-3 text-base {{ $icon ? 'pl-0' : 'pl-4' }}"
            x-text="selectedLabel"
            :class="value ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
        ></span>

        <span class="flex h-11 w-11 shrink-0 items-center justify-center text-gray-400 transition-transform duration-300 ease-out" :class="{ 'rotate-180': open }">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
    </button>

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

    <div
        x-show="open"
        @click.outside="closePicker()"
        @keydown="onWheelKeydown($event)"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
        class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-[5px] border border-gray-200 bg-white shadow-xl lg:rounded-[10px] dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2 dark:border-gray-700">
            <button type="button" @click="closePicker()" class="rounded-[5px] px-2 py-1 text-xs font-semibold text-gray-500 transition-colors duration-150 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">Cancel</button>
            <button type="button" @click="selectIndex(highlightedIndex, true)" class="rounded-[5px] px-2 py-1 text-xs font-bold text-primary-600 transition-colors duration-150 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/30">Done</button>
        </div>

        <div class="relative" style="height: 220px;">
            <div
                class="pointer-events-none absolute inset-x-0 top-1/2 z-10 -translate-y-1/2 border-y-2 border-primary-200 bg-primary-50/40 dark:border-primary-800 dark:bg-primary-900/20"
                style="height: 44px;"
            ></div>

            <div
                x-ref="wheel"
                @scroll="onWheelScroll()"
                tabindex="0"
                role="listbox"
                class="edn-wheel h-full overflow-y-auto scroll-smooth"
                style="padding-block: 88px; scroll-snap-type: y mandatory;"
            >
                <template x-for="(option, i) in options" :key="option.value">
                    <div
                        role="option"
                        :aria-selected="value === option.value"
                        @click="selectIndex(i, true)"
                        class="flex cursor-pointer select-none items-center justify-center text-center transition-all duration-150 ease-out"
                        style="height: 44px; scroll-snap-align: center;"
                        :class="{
                            'text-base font-bold text-primary-600 dark:text-primary-400': highlightedIndex === i,
                            'text-sm text-gray-500 opacity-60 dark:text-gray-400': Math.abs(highlightedIndex - i) === 1,
                            'text-sm text-gray-400 opacity-30 dark:text-gray-500': Math.abs(highlightedIndex - i) >= 2,
                        }"
                        x-text="option.label"
                    ></div>
                </template>
            </div>
        </div>
    </div>
</div>
