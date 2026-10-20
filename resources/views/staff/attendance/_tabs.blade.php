{{--
    The teacher's ways into the register: the daily register, its weekly,
    monthly and termly roll-ups, and, at a school that uses it, term totals
    entered by hand.

    @param string $current One of: daily, week, month, term-history, term.
--}}
@php
    $tabs = [
        'daily' => ['Daily', route('staff.attendance.index', [$school, 'class' => $className])],
        'week' => ['Weekly', route('staff.attendance.history', [$school, 'period' => 'week', 'class' => $className])],
        'month' => ['Monthly', route('staff.attendance.history', [$school, 'period' => 'month', 'class' => $className])],
        'term-history' => ['Termly', route('staff.attendance.history', [$school, 'period' => 'term', 'class' => $className])],
    ];

    if ($school->usesManualAttendance()) {
        $tabs['term'] = ['Term Totals', route('staff.attendance.term.index', [$school, 'class' => $className])];
    }
@endphp

<div class="flex max-w-full gap-1 overflow-x-auto rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800 sm:inline-flex">
    @foreach ($tabs as $key => [$label, $url])
        @if ($key === $current)
            <span class="shrink-0 whitespace-nowrap rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">{{ $label }}</span>
        @else
            <a href="{{ $url }}" class="shrink-0 whitespace-nowrap rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">{{ $label }}</a>
        @endif
    @endforeach
</div>
