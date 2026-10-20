<x-staff-layout page-title="Term Attendance" page-subtitle="Enter each student's days present, absent, late and excused for the term.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                Some figures could not be saved. Each count must be a whole number from 0 to 366.
            </div>
        @endif

        @include('staff.attendance._tabs', ['current' => 'term'])

        @if (! $school->usesManualAttendance())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Term attendance entry isn't turned on at your school</p>
                <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Keep using the daily register. Your School Admin can turn this on under Attendance &rarr; Term Totals.</small>
            </div>
        @elseif ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
                <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Ask your School Admin to assign you as the Class Teacher of a class to enter its attendance.</small>
            </div>
        @else
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-3 lg:rounded-[10px]">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Class</span>
                    <select name="class" onchange="this.form.submit()" class="w-full">
                        @foreach ($classes as $class)
                            <option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Session</span>
                    <select name="session" onchange="this.form.submit()" class="w-full">
                        @foreach ($sessionOptions as $option)
                            <option value="{{ $option }}" @selected($session === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Term</span>
                    <select name="term" onchange="this.form.submit()" class="w-full">
                        @foreach ($termOptions as $option)
                            <option value="{{ $option->value }}" @selected($term === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <noscript><button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Show</button></noscript>
            </form>

            <x-term-attendance-entry
                :action="route('staff.attendance.term.store', $school)"
                :rows="$rows"
                :hidden="['class_name' => $className, 'session' => $session, 'term' => $term->value]"
                :expected-days="$expectedDays"
                :academic-term="$academicTerm"
                :can-overwrite="$school->allowsTeacherAttendanceCorrections()"
                :term-label="$term->label().' '.$session"
            />
        @endif
    </div>
</x-staff-layout>
