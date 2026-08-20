@php $answersByQuestion = $attempt->answers->keyBy('cbt_question_id'); @endphp

<x-student-layout :page-title="$attempt->exam->title()" page-subtitle="Your result">
    <div class="space-y-6">
        <div class="rounded-[10px] border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @if ($attempt->auto_submitted)
                <span class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" /><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Auto-submitted — time expired
                </span>
            @endif
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Your Score</p>
            <p class="mt-1 text-4xl font-extrabold {{ $attempt->passed() ? 'text-green-600' : 'text-red-600' }}">{{ $attempt->percentage() }}%</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $attempt->correctCount() }} of {{ $attempt->total_questions }} correct &middot; {{ $attempt->passed() ? 'Passed' : 'Not passed' }} (pass mark {{ $attempt->exam->pass_mark }}%)</p>
        </div>

        @foreach ($questions as $index => $question)
            @php $answer = $answersByQuestion->get($question->id); @endphp
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $index + 1 }}. {{ $question->question_text }}</p>

                <div class="mt-4 space-y-2">
                    @foreach ($question->options as $option)
                        @php
                            $isSelected = $answer && $answer->cbt_question_option_id === $option->id;
                            $isCorrectOption = $option->is_correct;
                        @endphp
                        <div
                            class="flex items-center gap-3 rounded-[8px] border p-3 text-sm
                                {{ $isCorrectOption ? 'border-green-400 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : ($isSelected ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-700') }}"
                        >
                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $option->label }}.</span>
                            <span class="text-gray-700 dark:text-gray-200">{{ $option->option_text }}</span>
                            @if ($isCorrectOption)
                                <span class="ml-auto text-xs font-bold text-green-700 dark:text-green-400">Correct answer</span>
                            @elseif ($isSelected)
                                <span class="ml-auto text-xs font-bold text-red-700 dark:text-red-400">Your answer</span>
                            @endif
                        </div>
                    @endforeach
                    @if (! $answer)
                        <p class="text-xs italic text-gray-400 dark:text-gray-500">You did not answer this question.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-student-layout>
