@php
    $audienceCollection = collect($audienceOptions)->mapWithKeys(fn ($a) => [$a->value => $a->label()])->all();
@endphp

<x-dashboard-layout page-title="Events" page-subtitle="Plan and publish your school calendar.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Upcoming Events</p>
                    <x-stat-tooltip text="Events on your calendar that haven't started yet." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($upcomingCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex gap-1 rounded-[8px] border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-900">
                        @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $value => $label)
                            <a href="{{ route('events.index', ['when' => $value]) }}" class="rounded-[6px] px-3 py-1.5 text-xs font-semibold transition-colors duration-150 {{ $when === $value ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="when" value="{{ $when }}">
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search events..."
                            class="w-52"
                        >
                    </form>
                </div>

                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    New Event
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Event</th>
                            <th class="px-6 py-3 font-semibold">Date &amp; Time</th>
                            <th class="px-6 py-3 font-semibold">Location</th>
                            <th class="px-6 py-3 font-semibold">Audience</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($events as $event)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $event->title }}</p>
                                    @if ($event->description)
                                        <p class="mt-0.5 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400">{{ $event->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $event->starts_at->format('M j, Y'.($event->is_all_day ? '' : ' · g:ia')) }}
                                    @if ($event->ends_at) &rarr; {{ $event->ends_at->format('M j, Y g:ia') }} @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $event->location ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $event->audience->label() }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $event->uuid,
                                                'title' => $event->title,
                                                'description' => $event->description,
                                                'location' => $event->location,
                                                'audience' => $event->audience->value,
                                                'is_all_day' => $event->is_all_day,
                                                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                                                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete {{ $event->title }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No {{ $when === 'all' ? '' : $when.' ' }}events found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $events->links() }}
                </div>
            @endif
        </div>

        {{-- Add/Edit Event modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Event' : 'New Event'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('events.update', ['event' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('events.store') }}'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="title" label="Event Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.title : ''" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="starts_at" label="Starts At" type="datetime-local" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.starts_at : ''" required />
                        <x-text-field name="ends_at" label="Ends At" type="datetime-local" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.ends_at : ''" helper="Optional." />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field name="audience" label="Audience" required model="editing ? editing.audience : 'everyone'" :options="$audienceCollection" />
                        <x-text-field name="location" label="Location" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" x-model="editing ? editing.location : ''" helper="Optional." />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_all_day" value="1" :checked="editing ? editing.is_all_day : false" class="text-blue-600">
                        All-day event
                    </label>

                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="3" x-text="editing ? editing.description : ''" />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
