<x-staff-layout page-title="Exam Scores" page-subtitle="Enter and update scores for the subjects you teach.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Examination</th>
                            <th class="px-4 py-3 font-semibold">Class</th>
                            <th class="px-4 py-3 font-semibold">Session</th>
                            <th class="px-4 py-3 font-semibold">Term</th>
                            <th class="px-4 py-3 font-semibold">Subject</th>
                            <th class="px-4 py-3 font-semibold">Scored</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($examinations as $row)
                            @php [$examination, $subject] = [$row['examination'], $row['subject']]; @endphp
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $examination->name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->class_name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->session }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->term->label() }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $subject->name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $subject->scores()->count() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('staff.exams.scores.edit', [$school, $examination, $subject]) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                        Enter Scores
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No examinations match the subjects on your timetable yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-staff-layout>
