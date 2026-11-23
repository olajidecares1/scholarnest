<x-dashboard-layout page-title="All Saved Results" page-subtitle="Every result your school has saved, by academic session, term and class. Nothing needs to be uploaded or entered again.">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-end gap-2">
            <a
                href="{{ route('results.index') }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-chevron-left text-[12px]"></i>
                Back to Results
            </a>
        </div>

        {{-- One student, by admission number. --}}
        <form method="GET" action="{{ route('results.student') }}" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Find a student&rsquo;s results</h2>
            <small class="field-hint mt-0.5">Every term they have sat, in every class, with each session&rsquo;s cumulative result.</small>

            <div class="mt-4 flex flex-wrap items-start gap-3">
                <div class="min-w-[14rem] flex-1">
                    <label for="admission_number" class="sr-only">Admission number</label>
                    <input
                        id="admission_number"
                        type="text"
                        name="admission_number"
                        value="{{ old('admission_number') }}"
                        required
                        maxlength="100"
                        placeholder="Admission number, e.g. BFA/2026/0101"
                        class="w-full font-mono"
                    >
                    @error('admission_number')
                        <small class="mt-1 block text-xs font-semibold text-red-600">{{ $message }}</small>
                    @enderror
                </div>
                <button type="submit" class="btn rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    <i class="fa-solid fa-magnifying-glass btn-icon" aria-hidden="true"></i> Find Results
                </button>
            </div>
        </form>

        @if ($overview->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No results saved yet</p>
                <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Results appear here, term by term, as soon as scores are entered.</small>
            </div>
        @endif

        {{-- Session, then term, then class. The current session opens by
             default; earlier ones are a click away. --}}
        @foreach ($overview as $session => $terms)
            <details class="group rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" @if ($session === $currentSession || $loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                    <span class="flex items-center gap-3">
                        <i class="fa-solid fa-chevron-right text-[11px] text-gray-400 transition group-open:rotate-90" aria-hidden="true"></i>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $session }} Academic Session</span>
                        @if ($session === $currentSession)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">Current</span>
                        @endif
                    </span>
                    <span class="text-xs font-semibold text-gray-500">{{ $terms->count() }} term{{ $terms->count() === 1 ? '' : 's' }}</span>
                </summary>

                <div class="grid grid-cols-1 gap-4 border-t border-gray-100 p-5 dark:border-gray-700 lg:grid-cols-3">
                    @foreach ($terms as $termValue => $classes)
                        @php $term = \App\Enums\ExamTerm::from($termValue); @endphp
                        <div class="rounded-[8px] border border-gray-100 dark:border-gray-700">
                            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5 dark:border-gray-700">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $term->label() }}</h3>
                                <small class="text-xs text-gray-500">{{ $classes->sum('students') }} result{{ $classes->sum('students') === 1 ? '' : 's' }}</small>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($classes as $class)
                                    <li>
                                        <a
                                            href="{{ route('results.index', ['class' => $class['class_name'], 'session' => $session, 'term' => $term->value]) }}"
                                            class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                        >
                                            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $class['class_name'] }}</span>
                                            <span class="text-xs text-gray-500">
                                                {{ $class['students'] }} student{{ $class['students'] === 1 ? '' : 's' }}
                                                <i class="fa-solid fa-chevron-right ml-1 text-[10px]" aria-hidden="true"></i>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</x-dashboard-layout>
