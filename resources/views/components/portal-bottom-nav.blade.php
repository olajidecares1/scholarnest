@props([
    'primary' => [],
    'categories' => [],
    'badges' => [],
    'logoutUrl',
])

{{-- The app-style bottom bar.

     Fixed to the bottom below lg, where the desktop sidebar takes over. It
     carries only primary destinations plus More, four tabs at most, because a
     bottom bar with eight tabs is a menu that happens to be at the bottom, and
     nobody can hit a 40px-wide tab with a thumb.

     Everything else lives behind More, in the same categorised grid the home
     screen uses, so a feature is in the same place whichever way you reach it. --}}
<div x-data="{ moreOpen: false }" x-effect="$dispatch('edn-more-toggle', { open: moreOpen })" class="print:hidden">
    {{-- Shown at every width. These portals have no desktop layout to fall
         through to, so the bottom bar is the navigation, not a small-screen
         substitute for a sidebar. --}}
    {{-- A floating glass bar (.edn-glass-nav in resources/css/app.css),
         shared with the School Admin's. --}}
    {{-- Above the open More sheet (z-60), so its Close stays in reach. --}}
    <nav class="edn-bottom-nav edn-glass-nav z-40" :class="moreOpen && 'z-[60]'">
        <div class="grid" style="grid-template-columns: repeat({{ min(count($primary) + 1, 5) }}, minmax(0, 1fr));">
            @foreach ($primary as $item)
                <a
                    href="{{ $item['url'] }}"
                    @class([
                        'group flex flex-col items-center justify-center gap-1 py-2 transition-all duration-200 active:scale-95',
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

                    {{-- The active tab sits on a tinted pill (aria-current, in
                         .edn-glass-nav), not just a coloured label. --}}
                </a>
            @endforeach

            <button
                type="button"
                @click="moreOpen = ! moreOpen"
                :class="moreOpen && 'edn-more-open'"
                data-more-tab
                class="group flex flex-col items-center justify-center gap-1 py-2 text-gray-500 transition-colors duration-200 hover:text-primary-600 active:scale-95 dark:text-gray-400 dark:hover:text-primary-300"
                :aria-expanded="moreOpen.toString()"
            >
                {{-- Same wrapper as the other tabs' icons, so the labels line up.
                     While the menu is open, the dots turn into a cross. --}}
                <span class="edn-more-icon relative"><i class="fa-solid fa-ellipsis fa-fw text-[18px] leading-none"></i><i class="fa-solid fa-xmark fa-fw text-[18px] leading-none"></i></span>
                <small class="text-[10px] font-semibold leading-none" x-text="moreOpen ? 'Close' : 'More'">More</small>
            </button>
        </div>
    </nav>

    <x-app-more-sheet :categories="$categories" :badges="$badges" :logout-url="$logoutUrl" />
</div>
