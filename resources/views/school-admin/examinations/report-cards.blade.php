<x-dashboard-layout :page-title="'Report Cards · '.$examination->name" :page-subtitle="$examination->class_name.' · '.$examination->term->label().' · '.$examination->session">
    <div class="space-y-6">
        <a href="{{ route('examinations.show', $examination) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to {{ $examination->name }}
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Class Ranking</h2>
                <p class="field-hint mt-0.5">Ranked by average score across {{ $subjectCount }} subject(s).</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Position</th>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Subjects Graded</th>
                            <th class="px-6 py-3 font-semibold">Average</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($summaries as $summary)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 font-bold text-gray-900 dark:text-white">{{ $summary['position'] ?? 'N/A' }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('examinations.report-cards.show', [$examination, $summary['student']]) }}" class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">
                                        {{ $summary['student']->fullName() }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $summary['subjectsGraded'] }} / {{ $subjectCount }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('examinations.report-cards.show', [$examination, $summary['student']]) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View Report Card</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $examination->class_name }}.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
