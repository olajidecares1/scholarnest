<x-dashboard-layout :page-title="'Rooms · '.$hostel->name" page-subtitle="Manage rooms in this hostel.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('hostels.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Hostels
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">{{ $hostel->name }}</h2>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $hostel->gender->label() }} &middot; {{ $rooms->count() }} room(s)</p>
                </div>
                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Room
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Room</th>
                            <th class="px-6 py-3 font-semibold">Occupancy</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rooms as $room)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-6 py-3 font-semibold text-gray-900">Room {{ $room->room_number }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $room->allocations_count >= $room->capacity ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $room->allocations_count }} / {{ $room->capacity }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('hostels.students', $room) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50">Manage Students</a>
                                        <button
                                            type="button"
                                            @click="editing = @js(['uuid' => $room->uuid, 'room_number' => $room->room_number, 'capacity' => $room->capacity]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('hostels.rooms.destroy', $room) }}" onsubmit="return confirm('Remove Room {{ $room->room_number }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">No rooms yet. Click "Add Room" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add/Edit Room modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-[8px] bg-white p-6">
                <h3 class="text-sm font-bold text-gray-900" x-text="editing ? 'Edit Room' : 'Add Room'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('hostels.rooms.update', ['room' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('hostels.rooms.store', $hostel) }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="room_number" label="Room Number" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.room_number : ''" placeholder="e.g. 12" required />
                    <x-text-field name="capacity" label="Bed Capacity" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="1" max="50" value="4" x-model="editing ? editing.capacity : 4" required />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
