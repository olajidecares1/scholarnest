@php
    $classOptions = $academicLevels->flatMap->classes->pluck('name', 'name')->all();
@endphp

<x-dashboard-layout page-title="Assignments" page-subtitle="Set and track student assignments and homework.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <div class="w-40">
                        <x-select-field name="class" placeholder="All Classes" :selected="request('class')" :options="['' => 'All Classes'] + $classOptions" />
                    </div>
                    <input
                        type="text"
                        name="subject"
                        value="{{ request('subject') }}"
                        placeholder="Search subject..."
                        class="w-48"
                    >
                    <button type="submit" class="h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
                    @if (request('class') || request('subject'))
                        <a href="{{ route('assignments.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    New Assignment
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Due Date</th>
                            <th class="px-6 py-3 font-semibold">Graded</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($assignments as $assignment)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('assignments.show', $assignment) }}" class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ $assignment->title }}</a>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->class_name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->subject }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->due_date->format('M j, Y') }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $assignment->graded_count }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('assignments.show', $assignment) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage</a>
                                        <form method="POST" action="{{ route('assignments.destroy', $assignment) }}" onsubmit="return confirm('Delete {{ $assignment->title }}? All submissions will be removed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No assignments yet. Click "New Assignment" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($assignments->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $assignments->links() }}
                </div>
            @endif
        </div>

        {{-- New Assignment modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">New Assignment</h3>
                <form method="POST" action="{{ route('assignments.store') }}" class="mt-4 space-y-2">
                    @csrf
                    <x-text-field name="title" label="Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" placeholder="e.g. Algebra Worksheet" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field name="class_name" label="Class" required :options="$classOptions" />
                        <x-text-field name="subject" label="Subject" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" placeholder="e.g. Mathematics" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="due_date" label="Due Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" required />
                        <x-text-field name="max_score" label="Max Score" type="number" icon="M8 12.3l2.6 2.6L16.3 9" value="100" min="1" max="1000" required />
                    </div>

                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="3" />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
