<x-student-layout page-title="CBT Practice" page-subtitle="Practice past questions from the exam bank">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($examBodies as $examBody)
            <a href="{{ route('student.cbt-practice.show', [$school, $examBody]) }}" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ $examBody->code }}</span>
                <p class="mt-3 text-sm font-bold text-gray-900 dark:text-white">{{ $examBody->name }}</p>
                @if ($examBody->description)
                    <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $examBody->description }}</p>
                @endif
                <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $examBody->subjects_count }} subject(s) &middot; {{ $examBody->exams_count }} exam(s)</p>
            </a>
        @empty
            <p class="col-span-full py-10 text-center text-sm text-gray-500 dark:text-gray-400">No practice exams are available yet.</p>
        @endforelse
    </div>
</x-student-layout>
