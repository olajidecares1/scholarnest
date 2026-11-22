{{-- Test/Exam Score, from the class teacher's side.

     The same page as the School Admin's: Class, Academic Year, Term and
     Subject, the subject chips with how many students have a score, and the
     same marking grid (x-score-entry-grid), so a teacher and an administrator
     entering the same marks always produce the same result.

     The one difference is what can be picked. The Class list holds only the
     classes the School Admin assigned to this teacher, their own class first,
     and the subjects only those they may score: every subject of their own
     class, or just the subjects they teach in another. The controller checks
     every choice against that list; the page cannot widen it. --}}
<x-staff-layout page-title="Test/Exam Score" page-subtitle="Enter or update Test and Examination scores for your class.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300 lg:rounded-[10px]" role="alert">
                <p class="font-semibold">Some scores were not saved. Please check:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach (collect($errors->all())->unique()->take(8) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
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
            <small class="field-hint">Total, grade and remark come from this school's grading scale.</small>
        </div>

        @if (! $hasRows)
            <div class="rounded-[5px] border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 lg:rounded-[10px]">
                @if (! $hasAssignments)
                    You have not been assigned to a class or subject yet, so there are no
                    scores for you to enter. Ask your school administrator to assign you.
                @else
                    No subjects are set up for your class yet, so there is nothing to score.
                    Ask your school administrator to check the class's subjects.
                @endif
            </div>
        @else
            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                    <div class="w-48">
                        @if ($classOptions->count() > 1)
                            {{-- A teacher assigned more than one class picks
                                 between their own classes only. --}}
                            <label for="entry_class" class="field-label">Class</label>
                            <select id="entry_class" name="class_name" onchange="this.form.submit()" class="mt-1">
                                @foreach ($classOptions as $className)
                                    <option value="{{ $className }}" @selected($selectedClass === $className)>
                                        {{ $className }}{{ $classTeacherOf->contains($className) ? ' (class teacher)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{-- One assigned class: shown, selected, nothing to pick. --}}
                            <span class="field-label">Class</span>
                            <input type="hidden" name="class_name" value="{{ $selectedClass }}">
                            <p class="mt-1 flex h-[42px] items-center rounded-[8px] border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white">
                                {{ $selectedClass }}
                            </p>
                        @endif
                        <small class="field-hint">Assigned to you by the school administrator.</small>
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

                @if ($examination && $subject)
                    {{-- The School Admin's grid, unchanged. --}}
                    <x-score-entry-grid
                        :school="$school"
                        :examination="$examination"
                        :subject="$subject"
                        :students="$students"
                        :existing="$existing"
                        :action="route('staff.exams.scores.update', [$school, $examination, $subject])"
                        :subject-url="fn ($option) => route('staff.exams.index', [$school, 'class_name' => $examination->class_name, 'session' => $examination->session, 'term' => $examination->term->value, 'subject' => $option->name])"
                        method="PUT"
                    />
                @else
                    <small class="block p-6 text-sm text-gray-500 dark:text-gray-400">
                        There is no {{ $selectedTerm->label() }} examination for {{ $selectedClass }} in {{ $selectedSession }}, so there is nothing to score.
                    </small>
                @endif
            </div>
        @endif
    </div>
</x-staff-layout>
