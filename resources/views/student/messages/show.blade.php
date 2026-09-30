<x-student-layout :page-title="$notice->title" page-subtitle="Message from your school">
    <div class="space-y-6">
        <a href="{{ route('student.messages.index', $school) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
            <i class="fa-solid fa-chevron-left text-[14px] leading-none" aria-hidden="true"></i>
            Back to Messages
        </a>

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $notice->title }}</h2>
                <span class="text-xs text-gray-400">{{ $notice->created_at->diffForHumans() }}</span>
            </div>
            <small class="block mt-1 text-xs text-gray-400">{{ $notice->class_name ? "To {$notice->class_name}" : 'To all classes' }}</small>
            <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-200">{{ $notice->body }}</p>
        </div>
    </div>
</x-student-layout>
