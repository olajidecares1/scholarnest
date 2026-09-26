<x-student-layout page-title="My Timetable" page-subtitle="Weekly schedule for {{ $student->class_name }}">
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
                                @if ($entry->room)
                                    <small class="field-hint">{{ $entry->room }}</small>
                                @endif
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($entry->start_time)->format('h:i A') }} to {{ \Illuminate\Support\Carbon::parse($entry->end_time)->format('h:i A') }}
                            </span>
                        </div>
                    @empty
                        <small class="block px-5 py-6 text-center text-xs text-gray-400 dark:text-gray-500">No classes scheduled.</small>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-student-layout>
