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
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Enter Test (/{{ $subject->testMaxScore() }}) and Exam (/{{ $subject->examMaxScore() }}) for each student. Leave both blank to skip a student.</p>
            </div>

            <form method="POST" action="{{ route('examinations.scores.store', $subject) }}">
                @csrf

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Student</th>
                                <th class="px-6 py-3 font-semibold">Test / {{ $subject->testMaxScore() }}</th>
                                <th class="px-6 py-3 font-semibold">Exam / {{ $subject->examMaxScore() }}</th>
                                <th class="px-6 py-3 font-semibold">Total</th>
                                @unless ($school->automatic_grading)
                                    <th class="px-6 py-3 font-semibold">Grade Override</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($students as $student)
                                @php $score = $existing->get($student->id); @endphp
                                <tr
                                    class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    x-data="{ test: {{ $score?->test_score ?? 'null' }}, exam: {{ $score?->exam_score ?? 'null' }} }"
                                >
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($student->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                            @else
                                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <input
                                            type="number"
                                            name="test_scores[{{ $student->id }}]"
                                            x-model.number="test"
                                            min="0"
                                            max="{{ $subject->testMaxScore() }}"
                                            step="0.01"
                                            class="h-10 w-24 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        >
                                    </td>
                                    <td class="px-6 py-3">
                                        <input
                                            type="number"
                                            name="exam_scores[{{ $student->id }}]"
                                            x-model.number="exam"
                                            min="0"
                                            max="{{ $subject->examMaxScore() }}"
                                            step="0.01"
                                            class="h-10 w-24 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        >
                                    </td>
                                    <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white" x-text="(Number(test) || 0) + (Number(exam) || 0)"></td>
                                    @unless ($school->automatic_grading)
                                        <td class="px-6 py-3">
                                            @if ($school->gradeBands->isNotEmpty())
                                                <select name="grade_overrides[{{ $student->id }}]" class="h-10 w-24 rounded-[8px] border border-gray-300 px-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                                    <option value="">Auto</option>
                                                    @foreach ($school->gradeBands as $band)
                                                        <option value="{{ $band->letter }}" @selected($score?->grade_override === $band->letter)>{{ $band->letter }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" name="grade_overrides[{{ $student->id }}]" value="{{ $score?->grade_override }}" maxlength="3" placeholder="Auto" class="h-10 w-20 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                            @endif
                                        </td>
                                    @endunless
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $examination->class_name }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex justify-end border-t border-gray-100 p-6 dark:border-gray-700">
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                            Save Scores
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-dashboard-layout>
