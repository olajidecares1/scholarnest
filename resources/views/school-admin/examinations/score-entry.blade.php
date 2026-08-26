{{-- Test/Exam Score, from the administrator's side.

     Four choices - class, year, term, subject - and then the same marking grid
     a teacher uses. Deliberately the same grid: an administrator entering marks
     and a teacher entering marks must produce identical results, and two
     implementations of one calculation is how they stop doing that.

     An administrator is responsible for every class, so nothing here is
     narrowed by assignment the way the teacher's page is. --}}
<x-dashboard-layout page-title="Test/Exam Score" page-subtitle="Enter or update Test and Examination scores for any class.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        {{-- The system's limits, stated once. Not fields anybody fills in. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-[5px] border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-[13px] font-bold text-gray-900 dark:text-white">Scoring structure</p>
            <div class="flex flex-wrap items-center gap-2 text-[13px]">
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Test <span class="text-gray-400">max</span> 40</span>
                <span class="text-gray-400">+</span>
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Examination <span class="text-gray-400">max</span> 60</span>
                <span class="text-gray-400">=</span>
                <span class="rounded-[6px] bg-primary-50 px-2.5 py-1 font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Total 100</span>
            </div>
            <p class="field-hint">Total, grade and remark come from this school's grading scale.</p>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div class="w-48">
                    <label for="entry_class" class="field-label">Class</label>
                    <select id="entry_class" name="class_name" onchange="this.form.submit()" class="mt-1">
                        <option value="">Select a class</option>
                        @foreach ($classNames as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="entry_session" class="field-label">Academic Year</label>
                    <select id="entry_session" name="session" onchange="this.form.submit()" class="mt-1">
                        @foreach ($sessionOptions as $option)
                            <option value="{{ $option }}" @selected($selectedSession === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="entry_term" class="field-label">Term</label>
                    <select id="entry_term" name="term" onchange="this.form.submit()" class="mt-1">
                        <option value="">Select a term</option>
                        @foreach ($termOptions as $option)
                            <option value="{{ $option->value }}" @selected($selectedTerm === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($examination && $examination->subjects->isNotEmpty())
                    <div class="w-52">
                        <label for="entry_subject" class="field-label">Subject</label>
                        <select id="entry_subject" name="subject" onchange="this.form.submit()" class="mt-1">
                            @foreach ($examination->subjects as $option)
                                <option value="{{ $option->name }}" @selected($subject?->is($option))>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </form>

            @if ($subject)
                {{-- The teacher's grid, unchanged. --}}
                <x-score-entry-grid
                    :school="$school"
                    :examination="$examination"
                    :subject="$subject"
                    :students="$students"
                    :existing="$existing"
                    :action="route('examinations.scores.store', $subject)"
                    :subject-url="fn ($option) => route('examinations.score-entry', ['class_name' => $examination->class_name, 'session' => $examination->session, 'term' => $examination->term->value, 'subject' => $option->name])"
                    method="POST"
                />
            @else
                @if ($selectedClass === '' || $selectedTerm === null)
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">Choose a class and term to begin entering scores.</p>
                @elseif (! $examination)
                    {{-- Not a dead end. Sending an admin to another page to build
                         the paper, then back here to re-pick the same three
                         things, is how a working feature gets written off as
                         broken. --}}
                    <div class="p-6">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            No examination exists yet for {{ $selectedClass }} &middot; {{ $selectedTerm->label() }} &middot; {{ $selectedSession }}
                        </p>

                        @if ($offeredSubjectCount > 0)
                            <p class="field-hint mt-1">
                                Create it here and it will carry the
                                {{ $offeredSubjectCount }} subject{{ $offeredSubjectCount === 1 ? '' : 's' }}
                                this class is offered, each marked out of 100.
                            </p>

                            <form method="POST" action="{{ route('examinations.score-entry.create') }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="class_name" value="{{ $selectedClass }}">
                                <input type="hidden" name="session" value="{{ $selectedSession }}">
                                <input type="hidden" name="term" value="{{ $selectedTerm->value }}">

                                <button type="submit" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                                    <i class="fa-solid fa-plus text-[12px]"></i>
                                    Create it and start entering scores
                                </button>
                            </form>
                        @else
                            <p class="field-hint mt-1">
                                {{ $selectedClass }} has no subjects set up yet, so there is nothing to mark.
                                Add them on the <a href="{{ route('class-subjects.index') }}" class="font-semibold text-primary-600 hover:text-primary-700">Class Subjects</a> page first.
                            </p>
                        @endif
                    </div>
                @else
                    <p class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        {{ $examination->name }} has no subjects yet. Add its subjects from the Examinations page first.
                    </p>
                @endif
            @endif
        </div>
    </div>
</x-dashboard-layout>
