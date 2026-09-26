<x-student-layout page-title="My Subjects" page-subtitle="Subjects for {{ $student->class_name }}, from your assignments and exam results">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($subjects as $subject)
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $subject['name'] }}</p>
                    @if ($subject['average_percentage'] !== null)
                        <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $subject['average_percentage'] }}%</span>
                    @endif
                </div>
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ $subject['assignment_count'] }} {{ Str::plural('assignment', $subject['assignment_count']) }}</span>
                    <span>{{ $subject['exam_count'] }} {{ Str::plural('result', $subject['exam_count']) }}</span>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-sm text-gray-500 dark:text-gray-400">No subjects found for your class yet.</small>
            </div>
        @endforelse
    </div>
</x-student-layout>
