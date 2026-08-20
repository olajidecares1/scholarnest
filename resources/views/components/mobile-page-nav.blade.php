@props([
    'prev' => null,
    'next' => null,
    'currentLabel' => null,
])

@if ($prev || $next)
    <div class="flex items-center justify-between gap-2 border-b border-gray-200 bg-white px-3 py-2 print:hidden lg:hidden dark:border-gray-800 dark:bg-gray-900">
        @if ($prev)
            <a href="{{ $prev['url'] }}" class="flex min-w-0 items-center gap-1.5 rounded-[8px] px-2 py-1.5 text-xs font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-800">
                <i class="fa-solid fa-chevron-left shrink-0 text-[10px]"></i>
                <span class="max-w-[6rem] truncate sm:max-w-[10rem]">{{ $prev['label'] }}</span>
            </a>
        @else
            <span class="flex items-center gap-1.5 px-2 py-1.5 text-xs font-semibold text-gray-300 dark:text-gray-700">
                <i class="fa-solid fa-chevron-left shrink-0 text-[10px]"></i>
                <span>Previous</span>
            </span>
        @endif

        @if ($currentLabel)
            <span class="min-w-0 shrink truncate text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $currentLabel }}</span>
        @endif

        @if ($next)
            <a href="{{ $next['url'] }}" class="flex min-w-0 items-center gap-1.5 rounded-[8px] px-2 py-1.5 text-xs font-semibold text-gray-600 transition-colors duration-200 hover:bg-gray-50 active:scale-95 dark:text-gray-300 dark:hover:bg-gray-800">
                <span class="max-w-[6rem] truncate sm:max-w-[10rem]">{{ $next['label'] }}</span>
                <i class="fa-solid fa-chevron-right shrink-0 text-[10px]"></i>
            </a>
        @else
            <span class="flex items-center gap-1.5 px-2 py-1.5 text-xs font-semibold text-gray-300 dark:text-gray-700">
                <span>Next</span>
                <i class="fa-solid fa-chevron-right shrink-0 text-[10px]"></i>
            </span>
        @endif
    </div>
@endif
