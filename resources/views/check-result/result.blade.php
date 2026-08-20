@php
    $graded = $subjects->map(fn ($subject) => ['subject' => $subject, 'score' => $subject->scores->first()])->filter(fn ($row) => $row['score']);
    $average = $graded->isNotEmpty() ? round($graded->avg(fn ($row) => $row['score']->percentage()), 1) : null;
    $totalScore = $graded->sum(fn ($row) => (float) $row['score']->score);
    $pin = $usage->pin;
@endphp

<x-auth-layout :title="$student->fullName().' - Result - '.$school->name" simple>
    <x-auth-card class="!max-w-2xl">
        <div class="flex flex-wrap items-center gap-4">
            @if ($student->photoUrl())
                <img src="{{ $student->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700">
                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold text-gray-900">{{ $student->fullName() }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $student->admission_number }} &middot; {{ $examination->name }} &middot; {{ $examination->term->label() }} &middot; {{ $examination->session }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-gray-500">Average</p>
                <p class="text-2xl font-extrabold text-gray-900">{{ $average !== null ? $average.'%' : '—' }}</p>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-[8px] border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Subject</th>
                        <th class="px-4 py-3 font-semibold">Score</th>
                        <th class="px-4 py-3 font-semibold">Percentage</th>
                        <th class="px-4 py-3 font-semibold">Grade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($subjects as $subject)
                        @php $score = $subject->scores->first(); @endphp
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $subject->name }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($score)
                                    {{ rtrim(rtrim($score->score, '0'), '.') }} / {{ $subject->max_score }}
                                    @if ($score->test_score !== null && $score->exam_score !== null)
                                        <span class="text-xs text-gray-400">(T{{ rtrim(rtrim($score->test_score, '0'), '.') }}+E{{ rtrim(rtrim($score->exam_score, '0'), '.') }})</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $score ? $score->percentage().'%' : '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($score)
                                    <span class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700">{{ $score->grade() }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">No subjects have been graded for this examination yet.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($graded->isNotEmpty())
                    <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-900">
                        <tr>
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}</td>
                            <td class="px-4 py-3">{{ $average }}%</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="mt-4 rounded-[8px] bg-gray-50 p-3 text-center text-xs text-gray-500">
            This PIN has been used {{ $pin->uses_count }} of {{ $pin->max_uses }} times &middot; {{ $pin->remainingUses() }} check{{ $pin->remainingUses() === 1 ? '' : 's' }} remaining.
        </div>
    </x-auth-card>
</x-auth-layout>
