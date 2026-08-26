<x-dashboard-layout :page-title="'Scores · '.$subject->name" :page-subtitle="$examination->name.' · '.$examination->class_name">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('examinations.show', $examination) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to {{ $examination->name }}
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $subject->name }} &middot; Total {{ $subject->max_score }}</h2>
                <p class="field-hint mt-0.5">Enter the Test and Exam scores. Everything else on this page is worked out from them.</p>
            </div>

            <x-score-entry-grid
                :school="$school"
                :examination="$examination"
                :subject="$subject"
                :students="$students"
                :existing="$existing"
                :action="route('examinations.scores.store', $subject)"
                :subject-url="fn ($option) => route('examinations.scores', $option)"
            />
        </div>
    </div>
</x-dashboard-layout>
