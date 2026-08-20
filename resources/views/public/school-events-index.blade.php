<x-public-site-layout :school="$school" :website="$website" title="Events">
    <section class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-4xl">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Calendar</p>
                <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Upcoming Events</h1>
            </div>

            @if ($events->isEmpty())
                <p class="mt-10 text-center text-sm text-gray-500">No upcoming events right now. Check back soon.</p>
            @else
                <div class="mt-10 space-y-4">
                    @foreach ($events as $event)
                        <div class="flex items-start gap-4 rounded-[10px] border border-gray-200 p-5 shadow-sm">
                            <span class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-[10px] bg-primary-50 text-primary-700">
                                <span class="text-[10px] font-bold uppercase leading-none">{{ $event->starts_at->format('M') }}</span>
                                <span class="text-xl font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h2 class="text-base font-bold text-gray-900">{{ $event->title }}</h2>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600">{{ $event->audience->label() }}</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $event->starts_at->format('l, F j, Y') }}
                                    @if (! $event->is_all_day) &middot; {{ $event->starts_at->format('g:ia') }} @endif
                                    @if ($event->location) &middot; {{ $event->location }} @endif
                                </p>
                                @if ($event->description)
                                    <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $event->description }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $events->links() }}
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
