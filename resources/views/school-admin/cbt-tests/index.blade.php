<x-dashboard-layout page-title="CBT Tests" page-subtitle="Overview of every teacher-created CBT test in your school.">
    <div class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Teacher</th>
                    <th class="px-4 py-3">Class</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Questions</th>
                    <th class="px-4 py-3">Attempts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($tests as $test)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('cbt-tests.show', $test) }}" class="font-semibold text-primary-600 hover:text-primary-700">{{ $test->title }}</a>
                            <small class="field-hint">{{ $test->subject }}</small>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $test->staff->fullName() }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $test->class_name }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $test->status === \App\Enums\CbtTestStatus::Draft,
                                'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $test->status === \App\Enums\CbtTestStatus::Locked,
                                'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $test->status === \App\Enums\CbtTestStatus::Published,
                                'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $test->status === \App\Enums\CbtTestStatus::Archived,
                            ])>{{ $test->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $test->questions_count }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $test->attempts_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No CBT tests have been created by teaching staff yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dashboard-layout>
