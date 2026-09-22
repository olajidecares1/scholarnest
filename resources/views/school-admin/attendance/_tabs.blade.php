{{--
    The four ways into the register, in one place so that adding a fifth does
    not mean finding four copies of this bar.

    @param string $current One of: index, history, staff, check-in.
--}}
@php
    $tabs = [
        'index' => ['label' => 'Take Attendance', 'url' => route('attendance.index')],
        'history' => ['label' => 'History', 'url' => route('attendance.history')],
        'staff' => ['label' => 'Staff Register', 'url' => route('attendance.staff')],
        'check-in' => ['label' => 'QR Check-in', 'url' => route('attendance.check-in.edit')],
    ];
@endphp

<div class="flex flex-wrap gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
    @foreach ($tabs as $key => $tab)
        @if ($key === $current)
            <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">{{ $tab['label'] }}</span>
        @else
            <a href="{{ $tab['url'] }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">{{ $tab['label'] }}</a>
        @endif
    @endforeach
</div>
