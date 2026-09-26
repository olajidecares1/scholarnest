<x-dashboard-layout :page-title="'Room '.$room->room_number.' · '.$room->hostel->name" page-subtitle="Allocate students to this room.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('hostels.rooms', $room->hostel) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Rooms
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Room {{ $room->room_number }}</h2>
                    <small class="field-hint mt-0.5">{{ $allocations->count() }} / {{ $room->capacity }} beds occupied</small>
                </div>
                @if ($allocations->count() < $room->capacity)
                    <button
                        type="button"
                        @click="open = true"
                        class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                    >
                        <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                        Allocate Student
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Allocated</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($allocations as $allocation)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $allocation->student->fullName() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $allocation->student->class_name ?? 'N/A' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $allocation->allocated_date->format('M j, Y') }}</td>
                                <td class="px-6 py-3">
                                    <form method="POST" action="{{ route('hostels.allocations.destroy', $allocation) }}" onsubmit="return confirm('Vacate {{ $allocation->student->fullName() }} from this room?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Vacate</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No students allocated to this room yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Allocate Student modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Allocate Student</h3>
                <form method="POST" action="{{ route('hostels.students.store', $room) }}" class="mt-4 space-y-2">
                    @csrf
                    <x-select-field name="student_id" label="Student" required placeholder="Select a student" :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->fullName()])->all()" helper="Students already in a room aren't listed." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Allocate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
