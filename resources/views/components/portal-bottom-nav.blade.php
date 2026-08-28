@props([
    'primary' => [],
    'categories' => [],
    'badges' => [],
    'logoutUrl',
])

{{-- The app-style bottom bar.

     Fixed to the bottom below lg, where the desktop sidebar takes over. It
     carries only primary destinations plus More - four tabs at most, because a
     bottom bar with eight tabs is a menu that happens to be at the bottom, and
     nobody can hit a 40px-wide tab with a thumb.

     Everything else lives behind More, in the same categorised grid the home
     screen uses, so a feature is in the same place whichever way you reach it. --}}
<div x-data="{ moreOpen: false }" class="print:hidden lg:hidden">
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="mx-auto grid max-w-2xl" style="grid-template-columns: repeat({{ min(count($primary) + 1, 5) }}, minmax(0, 1fr));">
            @foreach ($primary as $item)
                <a
                    href="{{ $item['url'] }}"
                    @class([
                        'group flex flex-col items-center gap-1 py-2 transition-colors duration-200 active:scale-95',
                        'text-primary-600 dark:text-primary-300' => $item['active'],
                        'text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-300' => ! $item['active'],
                    ])
                    @if ($item['active']) aria-current="page" @endif
                >
                    <span class="relative">
                        <i class="{{ $item['icon'] }} fa-fw text-[18px] leading-none"></i>
                        @php $badge = $badges[$item['route']] ?? null; @endphp
                        @if ($badge)
                            <span class="absolute -right-2 -top-1.5 flex h-[16px] min-w-[16px] items-center justify-center rounded-full bg-red-600 px-1 text-[9px] font-bold leading-none text-white">{{ $badge > 99 ? '99+' : $badge }}</span>
                        @endif
                    </span>
                    <small class="text-[10px] font-semibold leading-none">{{ $item['label'] }}</small>

                    {{-- A coloured label alone is not enough of a signal in dark
                         mode, so the active tab also carries a bar. --}}
                    <span @class([
                        'h-0.5 w-6 rounded-full transition-colors duration-200',
                        'bg-primary-600 dark:bg-primary-300' => $item['active'],
                        'bg-transparent' => ! $item['active'],
                    ])></span>
                </a>
            @endforeach

            <button
                type="button"
                @click="moreOpen = true"
                class="group flex flex-col items-center gap-1 py-2 text-gray-500 transition-colors duration-200 hover:text-primary-600 active:scale-95 dark:text-gray-400 dark:hover:text-primary-300"
                :aria-expanded="moreOpen.toString()"
            >
                <i class="fa-solid fa-ellipsis fa-fw text-[18px] leading-none"></i>
                <small class="text-[10px] font-semibold leading-none">More</small>
                <span class="h-0.5 w-6 rounded-full bg-transparent"></span>
            </button>
        </div>
    </nav>

    {{-- The More sheet --}}
    <div
        x-show="moreOpen"
        x-transition.opacity
        @click="moreOpen = false"
        class="fixed inset-0 z-50 bg-gray-900/60"
        style="display: none;"
    ></div>

    <div
        x-show="moreOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        @keydown.escape.window="moreOpen = false"
        class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-[20px] border-t border-gray-200 bg-white pb-[calc(env(safe-area-inset-bottom)+1rem)] shadow-2xl dark:border-gray-700 dark:bg-gray-800"
        style="display: none;"
        role="dialog"
        aria-label="More"
    >
        <div class="sticky top-0 z-10 bg-white pb-2 pt-2.5 dark:bg-gray-800">
            <div class="mx-auto h-1.5 w-10 rounded-full bg-gray-300 dark:bg-gray-600"></div>
        </div>

        <div class="px-3 pb-2">
            <x-portal-app-menu :categories="$categories" :badges="$badges" />
        </div>

        <form method="POST" action="{{ $logoutUrl }}" class="mt-2 border-t border-gray-100 px-3 pt-3 dark:border-gray-700">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-red-50 px-4 py-3 text-sm font-bold text-red-600 transition-colors duration-200 hover:bg-red-100 active:bg-red-200 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40">
                <i class="fa-solid fa-right-from-bracket fa-fw"></i>
                Log Out
            </button>
        </form>
    </div>
</div>
