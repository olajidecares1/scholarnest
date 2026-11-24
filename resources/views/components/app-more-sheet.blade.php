@props([
    'categories' => [],
    'badges' => [],
    'logoutUrl',
    'title' => 'Menu',
])

{{-- The More sheet of every portal (School Admin, teacher, parent, pupil):
     a pane of frosted glass rising over a blurred page, with a search box
     and every feature as a coloured app tile, grouped (x-portal-app-menu).

     Lives inside the bottom bar's x-data, whose `moreOpen` opens it. --}}
<div
    x-show="moreOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="moreOpen = false"
    class="fixed inset-0 z-50 bg-gray-950/40 backdrop-blur-[4px] print:hidden"
    style="display: none;"
></div>

<div
    x-show="moreOpen"
    x-transition:enter="transition ease-[cubic-bezier(0.2,1.15,0.4,1)] duration-[560ms]"
    x-transition:enter-start="translate-y-full scale-[0.98]"
    x-transition:enter-end="translate-y-0 scale-100"
    x-transition:leave="transition ease-[cubic-bezier(0.4,0,1,1)] duration-[260ms]"
    x-transition:leave-start="translate-y-0"
    x-transition:leave-end="translate-y-full"
    @keydown.escape.window="moreOpen = false"
    x-on:edn-sheet-close="moreOpen = false"
    {{ $attributes->class(['edn-more-sheet edn-glass-sheet fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[88dvh] w-full max-w-3xl flex-col print:hidden']) }}
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    {{-- Drag this part down to close (resources/js/sheet-drag.js). --}}
    <div class="shrink-0 touch-none px-5 pb-3 pt-2.5" data-sheet-grab>
        <div class="mx-auto h-1.5 w-11 rounded-full bg-gray-400/50 dark:bg-gray-500/60"></div>

        <div class="edn-sheet-rise mt-3 flex items-center justify-between gap-3" style="--d: 80ms">
            <div class="min-w-0">
                <p class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</p>
                <small class="block text-xs text-gray-500 dark:text-gray-400">Everything in one place</small>
            </div>
            <button type="button" @click="moreOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-900/5 text-gray-600 transition-colors duration-200 hover:bg-gray-900/10 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15" aria-label="Close menu">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-2">
        <x-portal-app-menu :categories="$categories" :badges="$badges" searchable />
    </div>

    {{-- Room below Log Out for the bottom bar, which floats over the open sheet. --}}
    <form method="POST" action="{{ $logoutUrl }}" class="edn-sheet-rise shrink-0 border-t border-gray-900/5 px-4 pb-[calc(env(safe-area-inset-bottom)+5.75rem)] pt-3 dark:border-white/10" style="--d: 200ms">
        @csrf
        <button type="submit" class="btn flex w-full items-center justify-center gap-2 rounded-[14px] border border-red-200 bg-red-50/80 px-4 py-3 text-sm font-bold text-red-600 transition-all duration-200 hover:bg-red-100 active:scale-[0.98] dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/30">
            <i class="fa-solid fa-right-from-bracket fa-fw" aria-hidden="true"></i>
            Log Out
        </button>
    </form>
</div>
