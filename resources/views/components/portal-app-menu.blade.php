@props([
    'categories' => [],
    'badges' => [],
    // A search box over the tiles: on in the More sheet, where a School
    // Admin has thirty-odd features to find, off on a home screen.
    'searchable' => false,
])

{{-- The portal's features, laid out the way a phone app lays out its home
     screen: an icon, the name beneath it in <small>, grouped under a heading.

     Every portal uses it (School Admin, teacher, parent, pupil): in the More
     sheet (x-app-more-sheet) and on the teacher, parent and pupil home
     screens. Each section has its own colour, an app-icon gradient with a
     white glyph, so a section is found by colour before its name is read.
     The main (Academic) section and the ring on the page you are on take
     the school's own brand colour (--color-primary). Tiles ripple in as the menu opens
     (.edn-menu-tile in app.css), and stand still with reduce motion on. --}}
@php
    // A gradient per section, picked by what the section is about, so
    // "Academic" is the same colour in every portal. Full class strings, so
    // Tailwind sees them.
    $palette = [
        // The main section wears the school's own brand colour (--color-primary).
        'academic' => 'from-primary-500 to-primary-700 shadow-primary-500/30',
        'result' => 'from-primary-500 to-primary-700 shadow-primary-500/30',
        'learn' => 'from-sky-500 to-blue-600 shadow-sky-500/30',
        'child' => 'from-sky-500 to-blue-600 shadow-sky-500/30',
        'communi' => 'from-rose-500 to-pink-600 shadow-rose-500/30',
        'school life' => 'from-fuchsia-500 to-purple-600 shadow-fuchsia-500/30',
        'operation' => 'from-amber-400 to-orange-500 shadow-orange-500/30',
        'finance' => 'from-amber-400 to-orange-500 shadow-orange-500/30',
        'payment' => 'from-amber-400 to-orange-500 shadow-orange-500/30',
        'website' => 'from-emerald-500 to-teal-600 shadow-emerald-500/30',
        'people' => 'from-cyan-500 to-sky-600 shadow-cyan-500/30',
        'account' => 'from-slate-500 to-slate-700 shadow-slate-500/30',
        'setting' => 'from-slate-500 to-slate-700 shadow-slate-500/30',
    ];
    $fallback = [
        'from-primary-500 to-primary-700 shadow-primary-500/30',
        'from-sky-500 to-blue-600 shadow-sky-500/30',
        'from-rose-500 to-pink-600 shadow-rose-500/30',
        'from-emerald-500 to-teal-600 shadow-emerald-500/30',
        'from-amber-400 to-orange-500 shadow-orange-500/30',
        'from-fuchsia-500 to-purple-600 shadow-fuchsia-500/30',
        'from-cyan-500 to-sky-600 shadow-cyan-500/30',
    ];
    $toneFor = function (string $category, int $index) use ($palette, $fallback): string {
        $name = strtolower($category);

        foreach ($palette as $word => $tone) {
            if (str_contains($name, $word)) {
                return $tone;
            }
        }

        return $fallback[$index % count($fallback)];
    };
    $tile = 0;
@endphp

<div
    {{ $attributes->class(['edn-app-menu']) }}
    @if ($searchable)
        x-data="{
            q: '',
            has(label) { return label.toLowerCase().includes(this.q.trim().toLowerCase()); },
            any(labels) { return labels.some((label) => this.has(label)); },
        }"
    @endif
>
    @if ($searchable)
        <label class="edn-sheet-rise relative mb-4 block" style="--d: 130ms">
            <span class="sr-only">Search features</span>
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-gray-400" aria-hidden="true"></i>
            <input
                type="search"
                x-model="q"
                placeholder="Search features…"
                autocomplete="off"
                class="edn-menu-search w-full rounded-[14px] border-0 bg-gray-900/5 py-2.5 pl-10 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500/40 dark:bg-white/10 dark:text-white"
            >
        </label>
    @endif

    <div class="space-y-5 sm:space-y-6">
        @php $sectionIndex = 0; @endphp
        @foreach ($categories as $category => $items)
            @continue(empty($items))
            @php $tone = $toneFor((string) $category, $sectionIndex++); @endphp

            <section @if ($searchable) x-show="any(@js(collect($items)->pluck('label')->all()))" @endif>
                <div class="flex items-center gap-2 px-1">
                    <h2 class="shrink-0 text-[11px] font-bold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ $category }}</h2>
                    <span class="edn-menu-rule h-px flex-1 bg-gradient-to-r from-gray-200 to-transparent dark:from-gray-700"></span>
                </div>

                {{-- Three across on a 320px phone, four from 360px, more as a
                     tablet widens. --}}
                <div class="mt-3 grid grid-cols-3 gap-x-1 gap-y-3 min-[360px]:grid-cols-4 min-[540px]:grid-cols-5 sm:gap-x-2 md:grid-cols-6">
                    @foreach ($items as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if ($searchable) x-show="has(@js($item['label']))" @endif
                            @if (! empty($item['active'])) aria-current="page" @endif
                            title="{{ $item['description'] ?? $item['label'] }}"
                            class="edn-menu-tile group relative flex flex-col items-center gap-2 rounded-[16px] px-1 py-2 text-center"
                            style="--i: {{ $tile++ }}"
                        >
                            <span @class([
                                'relative flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[16px] bg-gradient-to-br shadow-lg transition-transform duration-200 ease-out group-hover:-translate-y-0.5 group-active:scale-90',
                                $tone,
                                'ring-2 ring-offset-2 ring-primary-500 ring-offset-white dark:ring-primary-300 dark:ring-offset-gray-900' => ! empty($item['active']),
                            ])>
                                {{-- The gloss along the top edge of an app icon. --}}
                                <span class="pointer-events-none absolute inset-x-0 top-0 h-1/2 rounded-t-[16px] bg-gradient-to-b from-white/30 to-transparent"></span>
                                <i class="{{ $item['icon'] }} fa-fw relative text-[20px] leading-none text-white drop-shadow-sm" aria-hidden="true"></i>

                                @php $badge = $badges[$item['route'] ?? ''] ?? null; @endphp
                                @if ($badge)
                                    <span class="absolute -right-1.5 -top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-900">{{ $badge > 99 ? '99+' : $badge }}</span>
                                @endif
                            </span>

                            <small @class([
                                'line-clamp-2 w-full text-[11px] font-semibold leading-tight',
                                'text-primary-700 dark:text-primary-200' => ! empty($item['active']),
                                'text-gray-700 dark:text-gray-200' => empty($item['active']),
                            ])>{{ $item['label'] }}</small>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($searchable)
            <small x-show="q.trim() !== '' && ! any(@js(collect($categories)->flatten(1)->pluck('label')->all()))" x-cloak class="block py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No feature matches "<span x-text="q.trim()"></span>".
            </small>
        @endif
    </div>
</div>
