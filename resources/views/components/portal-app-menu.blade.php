@props([
    'categories' => [],
    'badges' => [],
])

{{-- The portal's features, laid out the way a phone app lays out its home
     screen: an icon, the name beneath it in <small>, grouped under a heading.

     This replaced a grid of large cards. The cards were navigation wearing the
     costume of content - each one a bordered, shadowed, aspect-square block
     whose entire job was to be tapped once. Four of them filled a phone
     screen, so a pupil scrolled past three screens of chrome to reach
     "Results". At this size the same screen holds a dozen, grouped, and the
     grouping is what makes them findable rather than merely smaller.

     Colours come from --color-primary, which each layout emits from the
     school's own brand settings, so School A's icons are School A's colour. --}}
<div {{ $attributes->class(['space-y-6']) }}>
    @foreach ($categories as $category => $items)
        @continue(empty($items))

        <section>
            <h2 class="px-1 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $category }}</h2>

            <div class="mt-2.5 grid grid-cols-4 gap-1 sm:grid-cols-5 sm:gap-2 md:grid-cols-6 lg:grid-cols-8">
                @foreach ($items as $item)
                    <a
                        href="{{ $item['url'] }}"
                        @class([
                            'group relative flex flex-col items-center gap-1.5 rounded-[12px] px-1 py-3 text-center transition-colors duration-200',
                            'hover:bg-gray-100 active:bg-gray-200 dark:hover:bg-gray-700 dark:active:bg-gray-600',
                            'bg-gray-100 dark:bg-gray-700' => $item['active'],
                        ])
                        title="{{ $item['description'] }}"
                    >
                        <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-[14px] bg-primary-50 transition-transform duration-200 group-active:scale-90 dark:bg-primary-900/40">
                            <i class="{{ $item['icon'] }} fa-fw text-[19px] leading-none text-primary-600 dark:text-primary-300"></i>

                            @php $badge = $badges[$item['route']] ?? null; @endphp
                            @if ($badge)
                                <span class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-900">{{ $badge > 99 ? '99+' : $badge }}</span>
                            @endif
                        </span>

                        <small class="line-clamp-2 w-full text-[11px] font-semibold leading-tight text-gray-700 dark:text-gray-200">{{ $item['label'] }}</small>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
