<x-dashboard-layout :page-title="'Students · '.$route->name" page-subtitle="Assign students to this route.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('transport.routes.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Routes
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $route->name }}</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $assignments->count() }} student(s) assigned</p>
                </div>
                <button
                    type="button"
                    @click="open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Assign Student
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Pickup Point</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($assignments as $assignment)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $assignment->student->fullName() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->student->class_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->pickup_point ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <form method="POST" action="{{ route('transport.routes.assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove {{ $assignment->student->fullName() }} from this route?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No students assigned to this route yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Assign Student modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Assign Student</h3>
                <form method="POST" action="{{ route('transport.routes.students.store', $route) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-select-field name="student_id" label="Student" required placeholder="Select a student" :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->fullName()])->all()" helper="Students already on a route aren't listed." />
                    <x-text-field name="pickup_point" label="Pickup Point" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" helper="Optional." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
