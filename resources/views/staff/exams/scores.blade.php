<x-staff-layout :page-title="'Scores · '.$subject->name" :page-subtitle="$examination->name.' · '.$examination->class_name">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('staff.exams.index', $school) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Test/Exam Score
        </a>

        <div
            class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
            x-data="{
                search: '',
                matches(name, admission) {
                    return this.search === '' || (name + ' ' + admission).toLowerCase().includes(this.search.toLowerCase());
                },
            }"
        >
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $subject->name }} &middot; Total {{ $subject->max_score }}</h2>
                <p class="field-hint mt-0.5">Enter Test (/{{ $subject->testMaxScore() }}) and Exam (/{{ $subject->examMaxScore() }}) for each student. Leave both blank to skip a student.</p>

                <div class="relative mt-4 max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" /><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Search by name or Student ID"
                        class="w-full pl-9 pr-3"
                    >
                </div>
            </div>

            <x-score-entry-grid
                    :school="$school"
                    :examination="$examination"
                    :subject="$subject"
                    :students="$students"
                    :existing="$existing"
                    :action="route('staff.exams.scores.update', [$school, $examination, $subject])"
                    :subject-url="fn ($option) => route('staff.exams.scores.edit', [$school, $examination, $option])"
                    method="PUT"
                />
        </div>
    </div>
</x-staff-layout>
