<x-guardian-layout page-title="Exams & Results" :page-subtitle="$student->fullName().'\'s examination scores'" :active-child="$activeChild">
    <div class="space-y-6" x-data="{}">
        @forelse ($results as $examLabel => $scores)
            @php $examination = $scores->first()->subject->examination; @endphp
            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $examLabel }}</h2>
                    @php($locked = app(\App\Services\ResultAccessPolicy::class)->isLocked($student, $examination))

                    <x-result-token-gate
                        :examination="$examination"
                        :student="$student"
                        :withheld="$locked"
                        :unlocked="$unlocked[$examination->id] ?? false"
                        :unlock-url="route('guardian.children.results.unlock', [$school, $student, $examination])"
                    >
                        <button
                            type="button"
                            @click="$store.resultPreview.openPreview('{{ route('guardian.children.results.show', [$school, $student, $examination]) }}', '', '', '{{ route('guardian.children.results.print', [$school, $student, $examination]) }}', '{{ route('guardian.children.results.pdf', [$school, $student, $examination]) }}', 'report-card')"
                            class="shrink-0 rounded-[8px] border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300"
                        >
                            View Report Card
                        </button>
                    </x-result-token-gate>
                </div>

                @if ($locked)
                    <div class="flex items-start gap-2.5 border-b border-amber-100 bg-amber-50/60 px-5 py-3 dark:border-amber-900/40 dark:bg-amber-900/10">
                        <i class="fa-solid fa-circle-info mt-0.5 text-[11px] text-amber-600 dark:text-amber-400"></i>
                        <p class="text-[11.5px] leading-[1.55] text-amber-800 dark:text-amber-300">
                            {{ app(\App\Services\ResultAccessPolicy::class)->lockMessage($student, $examination) }}
                        </p>
                    </div>
                @endif

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($scores as $score)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $score->subject->name }}</span>
                            <span class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $score->score }}/{{ $score->subject->max_score }}
                                    @if ($score->test_score !== null && $score->exam_score !== null)
                                        (T{{ rtrim(rtrim($score->test_score, '0'), '.') }}+E{{ rtrim(rtrim($score->exam_score, '0'), '.') }})
                                    @endif
                                </span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $score->percentage() }}%</span>
                                <span class="w-8 rounded-full bg-primary-50 py-0.5 text-center text-[11px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $score->grade() }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">No results published yet.</p>
            </div>
        @endforelse
    </div>

    <x-result-preview-modal :can-send="false" />
</x-guardian-layout>
