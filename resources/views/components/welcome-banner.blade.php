@props([
    'photoUrl' => null,
    'initials' => '',
    'name',
    'roleLabel' => null,
    'subtitle' => null,
])

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
