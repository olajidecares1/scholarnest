@php
    $graded = $subjects->map(fn ($subject) => ['subject' => $subject, 'score' => $subject->scores->first()])->filter(fn ($row) => $row['score']);
    $average = $graded->isNotEmpty() ? round($graded->avg(fn ($row) => $row['score']->percentage()), 1) : null;
@endphp

<x-dashboard-layout :page-title="$student->fullName().' — Report Card'" :page-subtitle="$examination->name.' · '.$examination->class_name">
    <div class="space-y-6">
        <a href="{{ route('examinations.report-cards.index', $examination) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Report Cards
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center gap-4">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-xl font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $student->admission_number }} &middot; {{ $examination->name }} &middot; {{ $examination->term->label() }} &middot; {{ $examination->session }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="flex items-center justify-end gap-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Average</p>
                        <x-stat-tooltip text="Average percentage across every subject this student has been graded on for this examination." position="bottom" />
                    </div>
                    <p class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $average !== null ? $average.'%' : '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Subject Scores</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Score</th>
                            <th class="px-6 py-3 font-semibold">Percentage</th>
                            <th class="px-6 py-3 font-semibold">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($subjects as $subject)
                            @php $score = $subject->scores->first(); @endphp
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $subject->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($score) {{ rtrim(rtrim($score->score, '0'), '.') }} / {{ $subject->max_score }} @else — @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $score ? $score->percentage().'%' : '—' }}</td>
                                <td class="px-6 py-3">
                                    @if ($score)
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ $score->grade() }}</span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No subjects have been added to this examination yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
