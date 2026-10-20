<x-staff-layout page-title="Attendance" page-subtitle="Enter each student's attendance totals for the term.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex max-w-full gap-2 overflow-x-auto rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800 sm:w-fit">
            <a href="{{ route('staff.attendance.index', [$school, 'class' => $className]) }}" class="shrink-0 rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Daily</a>
            <a href="{{ route('staff.attendance.history', [$school, 'period' => 'term', 'class' => $className]) }}" class="shrink-0 rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Termly</a>
            <span class="shrink-0 rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Term Totals</span>
        </div>

        @if ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ask your School Admin to assign you as the Class Teacher of a class to record its attendance.</p>
            </div>
        @else
            <x-manual-attendance-sheet
                :rows="$rows"
                :academic-term="$academicTerm"
                :action="route('staff.attendance.manual.store', $school)"
                :class-name="$className"
                :session="$session"
                :term="$term"
                :classes="$classes"
                :filter-url="route('staff.attendance.manual', $school)"
                :register-url="route('staff.attendance.index', [$school, 'class' => $className])"
            />
        @endif
    </div>
</x-staff-layout>
