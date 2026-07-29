@php
    $classOptions = $academicLevels->flatMap->classes->pluck('name', 'name')->all();
@endphp

<x-dashboard-layout page-title="Examinations" page-subtitle="Manage exams, scores, and report cards.">
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
                    <div class="w-40">
                        <x-select-field name="term" placeholder="All Terms" :selected="request('term')" :options="['' => 'All Terms'] + collect($termOptions)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                    </div>
                    <button type="submit" class="h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
                    @if (request('class') || request('term'))
                        <a href="{{ route('examinations.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    New Examination
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Examination</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Term</th>
                            <th class="px-6 py-3 font-semibold">Session</th>
                            <th class="px-6 py-3 font-semibold">Subjects</th>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($examinations as $examination)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3">
                                    <a href="{{ route('examinations.show', $examination) }}" class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ $examination->name }}</a>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $examination->class_name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $examination->term->label() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $examination->session }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $examination->subjects_count }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $examination->exam_date?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('examinations.show', $examination) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage</a>
                                        <a href="{{ route('examinations.report-cards.index', $examination) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Report Cards</a>
                                        <form method="POST" action="{{ route('examinations.destroy', $examination) }}" onsubmit="return confirm('Delete {{ $examination->name }}? All subjects and scores will be removed.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No examinations yet. Click "New Examination" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($examinations->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $examinations->links() }}
                </div>
            @endif
        </div>

        {{-- New Examination modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">New Examination</h3>
                <form method="POST" action="{{ route('examinations.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <x-text-field name="name" label="Examination Name" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" placeholder="e.g. First Term Examination" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field name="class_name" label="Class" required :options="$classOptions" />
                        <x-select-field name="term" label="Term" required :options="collect($termOptions)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="session" label="Session" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" placeholder="e.g. 2025/2026" required />
                        <x-text-field name="exam_date" label="Exam Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Examination</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
