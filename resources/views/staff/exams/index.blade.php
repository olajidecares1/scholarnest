{{-- Where a class teacher manages Test and Examination marks.

     Pick a class, pick a subject, open the sheet. The two selectors are the
     route in rather than decoration: a teacher who class-teaches a full
     secondary class has a row per subject per term, and scrolling a flat list
     to find "Physics, this term" is the wrong way to start a marking session.

     Only Test and Examination are ever typed. Total, grade and remark are
     worked out - see the score grid itself. --}}
<x-staff-layout page-title="Test/Exam Score" page-subtitle="Enter and update Test and Examination scores for your class.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        {{-- The scoring structure, stated once. These are the system's limits,
             not anything the teacher fills in. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-[10px] border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-[13px] font-bold text-gray-900 dark:text-white">Scoring structure</p>
            <div class="flex flex-wrap items-center gap-2 text-[13px]">
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Test <span class="text-gray-400">max</span> 40</span>
                <span class="text-gray-400">+</span>
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Examination <span class="text-gray-400">max</span> 60</span>
                <span class="text-gray-400">=</span>
                <span class="rounded-[6px] bg-primary-50 px-2.5 py-1 font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Total 100</span>
            </div>
            <p class="field-hint">Total, grade and remark are calculated by the system.</p>
        </div>

        @if ($hasRows)
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="w-56">
                    <label for="filter_class" class="field-label">Class</label>
                    <select id="filter_class" name="class_name" onchange="this.form.submit()" class="mt-1">
                        <option value="">All my classes</option>
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-56">
                    <label for="filter_subject" class="field-label">Subject</label>
                    <select id="filter_subject" name="subject" onchange="this.form.submit()" class="mt-1">
                        <option value="">All subjects</option>
                        @foreach ($subjectOptions as $subjectName)
                            <option value="{{ $subjectName }}" @selected($selectedSubject === $subjectName)>{{ $subjectName }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedClass !== '' || $selectedSubject !== '')
                    <a href="{{ route('staff.exams.index', $school) }}" class="pb-2 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400">Clear</a>
                @endif
            </form>
        @endif

        <div class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Examination</th>
                            <th class="px-4 py-3 font-semibold">Class</th>
                            <th class="px-4 py-3 font-semibold">Session</th>
                            <th class="px-4 py-3 font-semibold">Term</th>
                            <th class="px-4 py-3 font-semibold">Subject</th>
                            <th class="px-4 py-3 font-semibold">Scored</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($examinations as $row)
                            @php [$examination, $subject] = [$row['examination'], $row['subject']]; @endphp
                            @php($entered = $subject->scores()->count())
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $examination->name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->class_name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->session }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $examination->term->label() }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $subject->name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entered }}</td>
                                <td class="px-4 py-3 text-right">
                                    {{-- Named for what it does to marks already there, so a
                                         teacher coming back to correct one knows this is the
                                         way in rather than looking for a separate edit. --}}
                                    <a href="{{ route('staff.exams.scores.edit', [$school, $examination, $subject]) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                        {{ $entered > 0 ? 'Enter / Update Scores' : 'Enter Scores' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                {{-- Which of the three reasons it is. "No examinations match
                                     your subjects" sent a teacher looking at the timetable
                                     when the actual cause was elsewhere. --}}
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if (! $hasAssignments)
                                        You have not been assigned to a class or subject yet, so there are no
                                        scores for you to enter. Ask your school administrator to assign you.
                                    @elseif ($hasRows)
                                        Nothing matches that class and subject. Clear the filters to see everything
                                        you can enter scores for.
                                    @else
                                        No examination has been created for your class yet.
                                        Scores can be entered here once the school sets one up for this term.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-staff-layout>
