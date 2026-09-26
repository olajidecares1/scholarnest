<x-student-layout page-title="My Tests" page-subtitle="School tests and examinations assigned to your class">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($tests as $test)
            @php $myAttempt = $test->attempts->first(); @endphp
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $test->title }}</p>
                <small class="field-hint mt-1">{{ $test->subject }} &middot; {{ $test->duration_minutes }} min &middot; {{ $test->questions_count }} question(s)</small>

                @if ($myAttempt && $myAttempt->isSubmitted())
                    <a href="{{ route('student.tests.attempts.show', [$school, $myAttempt]) }}" class="btn mt-4 inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        View Result ({{ $myAttempt->percentage() }}%)
                    </a>
                @elseif ($myAttempt)
                    <a href="{{ route('student.tests.attempts.show', [$school, $myAttempt]) }}" class="btn mt-4 inline-flex items-center gap-1.5 rounded-[8px] bg-amber-600 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-amber-700">
                        Continue Test
                    </a>
                @elseif ($test->isOpenForStudents())
                    <form method="POST" action="{{ route('student.tests.start', [$school, $test]) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-700">
                            Start Test
                        </button>
                    </form>
                @else
                    <span class="mt-4 inline-block rounded-[8px] bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Not yet available</span>
                @endif
            </div>
        @empty
            <small class="block col-span-full py-10 text-center text-sm text-gray-500 dark:text-gray-400">No tests assigned yet.</small>
        @endforelse
    </div>
</x-student-layout>
