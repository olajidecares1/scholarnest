<x-staff-layout page-title="My Timetable" page-subtitle="Your weekly teaching schedule">
    <div class="space-y-6">
        @foreach (range(1, 5) as $day)
            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'][$day] }}</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($entries->get($day, collect()) as $entry)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $entry->subject }}</p>
                                <p class="field-hint">{{ $entry->class_name }}@if ($entry->room) &middot; {{ $entry->room }} @endif</p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($entry->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($entry->end_time)->format('h:i A') }}
                            </span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-xs text-gray-400 dark:text-gray-500">No classes scheduled.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-staff-layout>
