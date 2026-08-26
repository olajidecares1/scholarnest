<x-student-layout :page-title="$examBody->name" page-subtitle="Choose an exam to practice">
    <div class="space-y-6">
        <a href="{{ route('student.cbt-practice.index', $school) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT Practice
        </a>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($exams as $exam)
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $exam->subject->name }} {{ $exam->year }}</p>
                    <p class="field-hint mt-1">{{ $exam->questions_count }} questions &middot; {{ $exam->duration_minutes }} mins &middot; Pass mark {{ $exam->pass_mark }}%</p>

                    <form method="POST" action="{{ route('student.cbt-practice.start', [$school, $exam]) }}" class="mt-4">
                        @csrf
                        <button
                            type="submit"
                            @if ($exam->questions_count === 0) disabled @endif
                            class="w-full rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-none"
                        >
                            Start Practice
                        </button>
                    </form>
                </div>
            @empty
                <p class="col-span-full py-10 text-center text-sm text-gray-500 dark:text-gray-400">No exams have been added for {{ $examBody->name }} yet.</p>
            @endforelse
        </div>
    </div>
</x-student-layout>
