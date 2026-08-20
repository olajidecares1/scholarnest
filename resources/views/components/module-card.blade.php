@props([
    'href',
    'label',
    'icon' => 'fa-circle',
    'badge' => null,
    'color' => 'text-primary-600 bg-primary-50 dark:bg-primary-900/20',
])

<a
    href="{{ $href }}"
    {{ $attributes->class(['group relative flex aspect-square w-full flex-col items-center justify-center gap-2 rounded-[10px] border border-gray-200 bg-white p-2.5 text-center shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:border-primary-200 hover:shadow-lg active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-800']) }}
>
    @if ($badge !== null && $badge !== '')
        <span class="absolute right-2 top-2 rounded-full bg-primary-600 px-1.5 py-0.5 text-[10px] font-bold text-white shadow-sm">{{ $badge }}</span>
    @endif

    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] {{ $color }} transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-3 sm:h-12 sm:w-12">
        <i class="fa-solid {{ $icon }} text-base sm:text-xl"></i>
    </span>

    <span class="line-clamp-2 min-w-0 text-[11px] font-bold leading-tight text-gray-900 dark:text-white sm:text-sm">{{ $label }}</span>
</a>
