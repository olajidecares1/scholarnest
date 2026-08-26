<x-dashboard-layout page-title="Timetable" page-subtitle="Manage each class's weekly schedule.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" class="w-56">
                <div x-data="{ classFilter: @js($selectedClass) }" x-init="$watch('classFilter', () => $el.closest('form').submit())">
                    <x-select-field
                        name="class"
                        label="Class"
                        model="classFilter"
                        :options="collect($classes)->mapWithKeys(fn ($name) => [$name => $name])->all()"
                        placeholder="Select a class"
                    />
                </div>
            </form>

            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Entry
            </button>
        </div>

        @if (! $selectedClass)
            <div class="rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Create a class under Academics before setting up a timetable.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
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
                                        <p class="field-hint">
                                            {{ \Illuminate\Support\Carbon::parse($entry->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($entry->end_time)->format('h:i A') }}
                                            @if ($entry->room)
                                                &middot; {{ $entry->room }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $entry->uuid,
                                                'class_name' => $entry->class_name,
                                                'day_of_week' => (string) $entry->day_of_week,
                                                'start_time' => \Illuminate\Support\Carbon::parse($entry->start_time)->format('H:i'),
                                                'end_time' => \Illuminate\Support\Carbon::parse($entry->end_time)->format('H:i'),
                                                'subject' => $entry->subject,
                                                'room' => $entry->room,
                                            ]); open = true"
                                            class="rounded-[8px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-gray-50 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5 5.5l3 3L8 19l-4 1 1-4 10.5-10.5z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </button>
                                        <form method="POST" action="{{ route('timetable.destroy', $entry) }}" onsubmit="return confirm('Remove {{ $entry->subject }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12M9.5 7V5a1 1 0 011-1h3a1 1 0 011 1v2M8 7l.5 12a1 1 0 001 1h5a1 1 0 001-1L16 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="px-5 py-6 text-center text-xs text-gray-400 dark:text-gray-500">No classes scheduled.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Add/Edit Timetable Entry modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Timetable Entry' : 'Add Timetable Entry'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('timetable.update', ['timetableEntry' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('timetable.store') }}'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-select-field
                        name="class_name"
                        label="Class"
                        required
                        model="editing ? editing.class_name : '{{ $selectedClass }}'"
                        :options="collect($classes)->mapWithKeys(fn ($name) => [$name => $name])->all()"
                        placeholder="Select a class"
                    />
                    <x-select-field
                        name="day_of_week"
                        label="Day"
                        required
                        model="editing ? editing.day_of_week : '1'"
                        :options="['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday']"
                        placeholder="Select a day"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <x-text-field name="start_time" label="Start Time" type="time" x-model="editing ? editing.start_time : ''" required />
                        <x-text-field name="end_time" label="End Time" type="time" x-model="editing ? editing.end_time : ''" required />
                    </div>

                    <x-text-field name="subject" label="Subject" icon="M4 19.5A2.5 2.5 0 016.5 17H20 M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" x-model="editing ? editing.subject : ''" placeholder="e.g. Mathematics" required />
                    <x-text-field name="room" label="Room" icon="M4 20V10.5L12 4l8 6.5V20" x-model="editing ? editing.room : ''" placeholder="e.g. Room 4" helper="Optional." />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
