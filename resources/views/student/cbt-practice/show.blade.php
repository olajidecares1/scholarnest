<x-student-layout :page-title="$examBody->name" page-subtitle="Pick a year, then the subject you want to practise">
    <div class="space-y-6">
        <a href="{{ route('student.cbt-practice.index', $school) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
            <i class="fa-solid fa-chevron-left text-xs"></i>
            Back to CBT Practice
        </a>

        @error('exam')
            <div class="rounded-[10px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ $message }}</div>
        @enderror

        @if ($years->isEmpty())
            <div class="rounded-[10px] border border-gray-200 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <i class="fa-solid fa-folder-open text-3xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No {{ $examBody->name }} past questions are available yet. Check back soon.</p>
            </div>
        @else
            {{-- The years come from the papers that exist, so this list is
                 whatever has been published, never a fixed run of years. --}}
            <div x-data="{ year: {{ $selectedYear }} }" class="space-y-5">
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-calendar-days mr-1.5 text-primary-600"></i>Choose a Year</p>
                    <p class="field-hint mt-1">{{ $years->count() }} year(s) of {{ $examBody->name }} past questions.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($years as $year)
                            <button
                                type="button"
                                @click="year = {{ $year }}"
                                class="rounded-[8px] border px-4 py-2 text-sm font-bold transition-all duration-200 hover:-translate-y-0.5"
                                :class="year === {{ $year }}
                                    ? 'border-primary-600 bg-primary-600 text-white shadow-md'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                            >
                                {{ $year }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach ($examsByYear as $year => $exams)
                    <div x-show="year === {{ $year }}" x-cloak class="space-y-3">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $examBody->code }} {{ $year }} &middot; {{ $exams->count() }} subject(s)</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($exams as $exam)
                                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $exam->subject->name }}</p>
                                    <p class="field-hint mt-1">
                                        <i class="fa-solid fa-list-ol mr-1"></i>{{ $exam->questions_count }} questions
                                        &middot; <i class="fa-regular fa-clock mr-1"></i>{{ $exam->duration_minutes }} mins
                                        &middot; Pass mark {{ $exam->pass_mark }}%
                                    </p>

                                    <form method="POST" action="{{ route('student.cbt-practice.start', [$school, $exam]) }}" class="mt-4">
                                        @csrf
                                        <button type="submit" class="w-full rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md">
                                            <i class="fa-solid fa-play mr-1.5"></i>Start {{ $year }} Practice
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-student-layout>
