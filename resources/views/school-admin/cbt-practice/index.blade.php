<x-dashboard-layout page-title="CBT Practice" page-subtitle="Browse the practice exam bank available to your students.">
    <div class="space-y-6">
        <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 lg:rounded-[10px]">
            This is a read-only view of EduNest's shared practice question bank. Exam bodies, subjects, and questions are curated centrally and shared across every school.
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($examBodies as $examBody)
                <a href="{{ route('cbt-practice.show', $examBody) }}" class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-md lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">{{ $examBody->code }}</span>
                    </div>
                    <p class="mt-3 text-sm font-bold text-gray-900">{{ $examBody->name }}</p>
                    @if ($examBody->description)
                        <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $examBody->description }}</p>
                    @endif
                    <p class="mt-3 text-xs font-medium text-gray-500">{{ $examBody->subjects_count }} subject(s) &middot; {{ $examBody->exams_count }} exam(s)</p>
                </a>
            @empty
                <p class="col-span-full py-10 text-center text-sm text-gray-500">No exam bodies have been added to the practice bank yet.</p>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
