<x-dashboard-layout page-title="Attendance" page-subtitle="Term totals: each student's school days, days present and days absent for the term.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                Some figures could not be saved. School days must be a whole number from 1 to 366, and each count a whole number from 0 to 366.
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('school-admin.attendance._tabs', ['current' => 'term'])
        </div>

        {{-- The setting. Optional, per school, on every plan, and off by
             default. It sits beside the daily register and QR check-in: both
             keep working whichever way this is set. --}}
        <div id="manual-attendance" class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Attendance Method</h2>
            <small class="field-hint mt-1">
                Choose how each student's attendance for the term is recorded. With manual entry, class teachers (and you) type each student's
                school days, days present and days absent for the term, and report cards use those totals. The daily register and QR check-in
                carry on working either way, and nothing is deleted when you switch back.
            </small>

            <form method="POST" action="{{ route('attendance.term.settings') }}" class="mt-4 space-y-4" x-data="{ enabled: @js((bool) old('manual_attendance_enabled', $school->usesManualAttendance())) }">
                @csrf @method('PUT')

                <fieldset class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <legend class="sr-only">Attendance method</legend>
                    <label class="flex cursor-pointer items-start gap-3 rounded-[8px] border p-4 text-sm transition-colors duration-150"
                        x-bind:class="! enabled ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'">
                        <input type="radio" name="manual_attendance_enabled" value="0" x-on:change="enabled = false" @checked(! old('manual_attendance_enabled', $school->usesManualAttendance())) class="mt-0.5 w-4 text-blue-600">
                        <span>
                            <span class="font-semibold text-gray-900 dark:text-white">Daily register</span>
                            <small class="block text-xs text-gray-500 dark:text-gray-400">Mark students present or absent each day (or by QR check-in). Report cards count the register.</small>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-[8px] border p-4 text-sm transition-colors duration-150"
                        x-bind:class="enabled ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'">
                        <input type="radio" name="manual_attendance_enabled" value="1" x-on:change="enabled = true" @checked(old('manual_attendance_enabled', $school->usesManualAttendance())) class="mt-0.5 w-4 text-blue-600">
                        <span>
                            <span class="font-semibold text-gray-900 dark:text-white">Manual term entry</span>
                            <small class="block text-xs text-gray-500 dark:text-gray-400">Enter each student's totals for the term. Report cards use them, and the register for any student without them.</small>
                        </span>
                    </label>
                </fieldset>

                {{-- Hidden, not removed, while off: still sent, so turning the
                     feature off keeps the school's last answer. --}}
                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200" x-show="enabled" x-cloak>
                    <input type="checkbox" name="manual_attendance_teacher_edits" value="1" @checked(old('manual_attendance_teacher_edits', $school->allowsTeacherAttendanceCorrections())) class="mt-0.5 w-4 text-blue-600">
                    <span>
                        Class teachers may correct totals they have already saved
                        <small class="block text-xs text-gray-500 dark:text-gray-400">When unticked, a teacher enters each student once and only you can change it afterwards.</small>
                    </span>
                </label>

                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save</button>
            </form>
        </div>

        @if ($school->usesManualAttendance())
            @if ($classNames === [])
                <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No classes yet</p>
                    <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Add classes under Academics to start entering attendance.</small>
                </div>
            @else
                <form method="GET" class="grid grid-cols-1 gap-3 rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-3 lg:rounded-[10px]">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">Class</span>
                        <select name="class" onchange="this.form.submit()" class="w-full">
                            @foreach ($classNames as $option)
                                <option value="{{ $option }}" @selected($className === $option)>{{ $option }}</option>
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

                <div class="flex justify-end">
                    <a href="{{ route('attendance.term.export', ['class' => $className, 'session' => $session, 'term' => $term->value]) }}"
                        class="btn rounded-[8px] border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-file-csv btn-icon" aria-hidden="true"></i> Download {{ $className }} report (CSV)
                    </a>
                </div>

                <x-term-attendance-entry
                    :action="route('attendance.term.store')"
                    :rows="$rows"
                    :hidden="['class_name' => $className, 'session' => $session, 'term' => $term->value]"
                    :expected-days="$expectedDays"
                    :academic-term="$academicTerm"
                    :can-overwrite="true"
                    :term-label="$term->label().' '.$session"
                    :term-dates-url="route('academics.index')"
                />
            @endif
        @endif
    </div>
</x-dashboard-layout>
