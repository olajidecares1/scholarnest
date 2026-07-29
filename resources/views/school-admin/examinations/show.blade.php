<x-dashboard-layout :page-title="$examination->name" :page-subtitle="$examination->class_name.' · '.$examination->term->label().' · '.$examination->session">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('examinations.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to Examinations
            </a>
            <a href="{{ route('examinations.report-cards.index', $examination) }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                View Report Cards
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Students in {{ $examination->class_name }}</p>
                    <x-stat-tooltip text="Active students in this examination's class — the pool eligible to have scores entered." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($studentCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Subjects</p>
                    <x-stat-tooltip text="Number of subjects added to this examination so far." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ $subjects->count() }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-green-600 dark:text-green-400">Exam Date</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ $examination->exam_date?->format('M j') ?? '—' }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Subjects</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Add subjects, then enter scores for each one.</p>
                </div>
                <button
                    type="button"
                    @click="open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Subject
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Max Score</th>
                            <th class="px-6 py-3 font-semibold">Scores Entered</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($subjects as $subject)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $subject->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $subject->max_score }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $subject->scores_count }} / {{ $studentCount }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('examinations.scores', $subject) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Enter Scores</a>
                                        <form method="POST" action="{{ route('examinations.subjects.destroy', $subject) }}" onsubmit="return confirm('Remove {{ $subject->name }}? All scores for it will be deleted.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No subjects yet. Click "Add Subject" to start.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add Subject modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Subject</h3>
                <form method="POST" action="{{ route('examinations.subjects.store', $examination) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-text-field name="name" label="Subject Name" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" placeholder="e.g. Mathematics" required />
                    <x-text-field name="max_score" label="Max Score" type="number" icon="M8 12.3l2.6 2.6L16.3 9" value="100" min="1" max="1000" required />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
