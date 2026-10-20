<x-dashboard-layout page-title="Attendance" page-subtitle="Enter or correct each student's attendance totals for the term.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex max-w-full gap-2 overflow-x-auto rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800 sm:w-fit">
            <a href="{{ route('attendance.index') }}" class="shrink-0 rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Take Attendance</a>
            <a href="{{ route('attendance.history') }}" class="shrink-0 rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">History</a>
            <span class="shrink-0 rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Term Totals</span>
        </div>

        @if (! $school->manual_attendance_enabled)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Manual attendance entry is switched off</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Turn on <strong>Manual Attendance Entry</strong> under <a href="{{ route('settings.index') }}" class="font-semibold text-blue-600 hover:underline">Settings</a>
                    to let class teachers enter each student's term totals. The daily register keeps working either way.
                </p>
            </div>
        @elseif ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                Add your classes under Academics first.
            </div>
        @else
            <x-manual-attendance-sheet
                :rows="$rows"
                :academic-term="$academicTerm"
                :action="route('attendance.manual.store')"
                :class-name="$className"
                :session="$session"
                :term="$term"
                :classes="$classes"
                :filter-url="route('attendance.manual')"
                :register-url="route('attendance.index', ['class' => $className])"
                :enforce-locks="false"
            />
        @endif
    </div>
</x-dashboard-layout>
