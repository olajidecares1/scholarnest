@props([
    'text',
    'position' => 'top',
])

@php
    $isBottom = $position === 'bottom';
@endphp

<span class="group/tip relative inline-flex shrink-0">
    <svg class="h-3.5 w-3.5 cursor-help text-gray-300 transition-colors duration-150 hover:text-gray-400 dark:text-gray-600 dark:hover:text-gray-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
        <path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
    </svg>
    <span
        @class([
            'pointer-events-none absolute left-1/2 z-30 w-48 -translate-x-1/2 rounded-[8px] bg-gray-900 px-3 py-2 text-xs font-medium normal-case leading-snug text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover/tip:opacity-100 dark:bg-gray-700',
            'bottom-full mb-2' => ! $isBottom,
            'top-full mt-2' => $isBottom,
        ])
    >
        {{ $text }}
        <span
            @class([
                'absolute left-1/2 -translate-x-1/2 border-4 border-transparent',
                'top-full border-t-gray-900 dark:border-t-gray-700' => ! $isBottom,
                'bottom-full border-b-gray-900 dark:border-b-gray-700' => $isBottom,
            ])
        ></span>
    </span>
</span>
