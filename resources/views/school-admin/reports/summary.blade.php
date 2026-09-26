<x-dashboard-layout page-title="Generate Report" page-subtitle="A snapshot summary of your school, ready to print or save as PDF.">
    <div class="space-y-6">
        <div class="flex justify-end print:hidden">
            <button
                type="button"
                onclick="window.print()"
                class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <i class="fa-solid fa-print text-[14px] leading-none" aria-hidden="true"></i>
                Print / Save as PDF
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 pb-6 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-12 w-12 rounded-[8px] object-cover">
                    @endif
                    <div>
                        <h1 class="text-lg font-bold text-gray-900 dark:text-white">{{ $school->name }}</h1>
                        <small class="field-hint">{{ $school->current_session ?? 'Session not set' }}</small>
                    </div>
                </div>
                <div class="text-right">
                    <small class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">School Summary Report</small>
                    <small class="block mt-0.5 text-xs text-gray-400 dark:text-gray-500">Generated {{ $generatedAt->format('M j, Y g:ia') }}</small>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([
                    ['label' => 'Total Students', 'value' => number_format($totalStudents), 'sub' => number_format($activeStudents).' active'],
                    ['label' => 'Teachers & Staff', 'value' => number_format($totalStaff)],
                    ['label' => 'Classes', 'value' => number_format($totalClasses)],
                    ['label' => 'Attendance (30 Days)', 'value' => $attendanceAverage !== null ? $attendanceAverage.'%' : 'N/A'],
                ] as $stat)
                    <div class="rounded-[8px] border border-gray-100 p-4 text-center dark:border-gray-700">
                        <p class="text-2xl font-extrabold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                        <small class="block mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</small>
                        @if (! empty($stat['sub']))
                            <small class="block text-xs text-gray-400 dark:text-gray-500">{{ $stat['sub'] }}</small>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Finance</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between border-b border-gray-50 pb-2 dark:border-gray-700/50"><dt class="text-gray-500 dark:text-gray-400">Total Invoiced</dt><dd class="font-semibold text-gray-900 dark:text-white">&#8358;{{ number_format($totalInvoiced, 2) }}</dd></div>
                        <div class="flex justify-between border-b border-gray-50 pb-2 dark:border-gray-700/50"><dt class="text-gray-500 dark:text-gray-400">Total Collected</dt><dd class="font-semibold text-green-600">&#8358;{{ number_format($totalCollected, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Outstanding</dt><dd class="font-semibold text-red-600">&#8358;{{ number_format($totalOutstanding, 2) }}</dd></div>
                    </dl>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Academic Performance</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between border-b border-gray-50 pb-2 dark:border-gray-700/50"><dt class="text-gray-500 dark:text-gray-400">Term Average Score</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ $examAverage !== null ? $examAverage.'%' : 'N/A' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Students with Graded Scores</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($gradedStudentCount) }}</dd></div>
                    </dl>
                </div>
            </div>

            <small class="block mt-8 border-t border-gray-100 pt-4 text-center text-xs text-gray-400 dark:border-gray-700 dark:text-gray-500">
                &copy; {{ now()->year }} {{ config('app.name', 'AkademicNest') }} School Management System.
            </small>
        </div>
    </div>
</x-dashboard-layout>
