<x-student-layout page-title="Assignments" page-subtitle="Assignments for {{ $student->class_name }}">
    <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($assignments as $assignment)
                @php $submission = $assignment->submissions->first(); @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $assignment->title }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $assignment->subject }} &middot; Due {{ $assignment->due_date->format('M j, Y') }}</p>
                        @if ($assignment->description)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($assignment->description, 140) }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @if ($submission)
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $submission->status->badgeClasses() }}">{{ $submission->status->label() }}</span>
                            @if ($submission->score !== null)
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $submission->score }}/{{ $assignment->max_score }}</span>
                            @endif
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">Not Submitted</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No assignments yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
</x-student-layout>
