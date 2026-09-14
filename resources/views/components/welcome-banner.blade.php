@props([
    'photoUrl' => null,
    'initials' => '',
    'name',
    'roleLabel' => null,
    'subtitle' => null,

    // The three portals are phone interfaces, where this banner was costing
    // roughly 190px, a third of a small screen, before the first thing a
    // teacher came to do. Compact halves that and grows back on a tablet.
    // Opt-in, so the School Admin and AkademicNest Team dashboards that share this
    // component keep the banner they had.
    'compact' => false,
])

@if ($compact)
    <div class="relative overflow-hidden rounded-[10px] bg-gradient-to-r from-primary-700 to-primary-900 p-4 text-white shadow-md sm:p-6">
        <div class="relative flex items-center gap-3">
            <span class="flex aspect-square h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[12px] border-2 border-white/30 bg-white/10 text-base font-bold sm:h-16 sm:w-16 sm:text-xl">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" class="h-full w-full object-cover" alt="">
                @else
                    {{ $initials }}
                @endif
            </span>

            <div class="min-w-0 flex-1">
                <p class="truncate text-base font-extrabold leading-tight sm:text-xl">{{ $name }}</p>

                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if ($roleLabel)
                        <span class="inline-block rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">{{ $roleLabel }}</span>
                    @endif

                    @if ($subtitle)
                        <span class="min-w-0 text-[11px] leading-snug text-primary-100 sm:text-xs">{{ $subtitle }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    <div class="relative overflow-hidden rounded-[10px] bg-gradient-to-r from-primary-700 to-primary-900 p-6 text-white shadow-lg sm:p-8">
        <div class="relative flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-lg font-bold sm:text-2xl">Welcome back,</p>
                <p class="mt-0.5 truncate text-xl font-extrabold sm:text-3xl">{{ $name }} 👋</p>
                @if ($roleLabel)
                    <span class="mt-1.5 inline-block rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide">{{ $roleLabel }}</span>
                @endif
                @if ($subtitle)
                    <p class="mt-1.5 text-sm text-primary-100">{{ $subtitle }}</p>
                @endif
            </div>

            <span class="flex aspect-square h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[14px] border-2 border-white/30 bg-white/10 text-2xl font-bold shadow-lg sm:h-28 sm:w-28 sm:text-4xl">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" class="h-full w-full object-cover">
                @else
                    {{ $initials }}
                @endif
            </span>
        </div>
    </div>
@endif
