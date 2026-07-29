<x-dashboard-layout page-title="Generate Report" page-subtitle="A snapshot summary of your school, ready to print or save as PDF.">
    <div class="space-y-6">
        <div class="flex justify-end print:hidden">
            <button
                type="button"
                onclick="window.print()"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 9V4h12v5M6 18H4.5A1.5 1.5 0 013 16.5v-5A1.5 1.5 0 014.5 10h15a1.5 1.5 0 011.5 1.5v5a1.5 1.5 0 01-1.5 1.5H18M6 14h12v7H6v-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Print / Save as PDF
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-8 shadow-sm lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 pb-6">
                <div class="flex items-center gap-3">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-12 w-12 rounded-[8px] object-cover">
                    @endif
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">{{ $school->name }}</h1>
                        <p class="text-xs text-gray-500">{{ $school->current_session ?? 'Session not set' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">School Summary Report</p>
                    <p class="mt-0.5 text-xs text-gray-400">Generated {{ $generatedAt->format('M j, Y g:ia') }}</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([
                    ['label' => 'Total Students', 'value' => number_format($totalStudents), 'sub' => number_format($activeStudents).' active'],
                    ['label' => 'Teachers & Staff', 'value' => number_format($totalStaff)],
                    ['label' => 'Classes', 'value' => number_format($totalClasses)],
                    ['label' => 'Attendance (30 Days)', 'value' => $attendanceAverage !== null ? $attendanceAverage.'%' : '—'],
                ] as $stat)
                    <div class="rounded-[8px] border border-gray-100 p-4 text-center">
                        <p class="text-2xl font-extrabold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs font-medium text-gray-500">{{ $stat['label'] }}</p>
                        @if (! empty($stat['sub']))
                            <p class="text-xs text-gray-400">{{ $stat['sub'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Finance</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between border-b border-gray-50 pb-2"><dt class="text-gray-500">Total Invoiced</dt><dd class="font-semibold text-gray-900">&#8358;{{ number_format($totalInvoiced, 2) }}</dd></div>
                        <div class="flex justify-between border-b border-gray-50 pb-2"><dt class="text-gray-500">Total Collected</dt><dd class="font-semibold text-green-600">&#8358;{{ number_format($totalCollected, 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Outstanding</dt><dd class="font-semibold text-red-600">&#8358;{{ number_format($totalOutstanding, 2) }}</dd></div>
                    </dl>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-gray-900">Academic Performance</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between border-b border-gray-50 pb-2"><dt class="text-gray-500">Term Average Score</dt><dd class="font-semibold text-gray-900">{{ $examAverage !== null ? $examAverage.'%' : '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Students with Graded Scores</dt><dd class="font-semibold text-gray-900">{{ number_format($gradedStudentCount) }}</dd></div>
                    </dl>
                </div>
            </div>

            <p class="mt-8 border-t border-gray-100 pt-4 text-center text-xs text-gray-400">
                &copy; {{ now()->year }} {{ config('app.name', 'EduNest') }} School Management System.
            </p>
        </div>
    </div>
</x-dashboard-layout>
