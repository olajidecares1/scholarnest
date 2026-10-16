{{-- A pupil's term attendance totals, as the school entered them, for the
     student's and the guardian's own pages. Renders nothing when there are
     none, so a school that doesn't use manual attendance sees no change. --}}
@props(['totals'])

@if ($totals->isNotEmpty())
    <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
            <p class="text-sm font-bold text-gray-900 dark:text-white">Term Attendance</p>
            <small class="field-hint">Days for each term, as recorded by the school.</small>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($totals as $total)
                <div class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $total->term->label() }} &middot; {{ $total->session }}</p>
                        <small class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $total->totalDays() }} days &middot; {{ $total->percent() !== null ? $total->percent().'% attended' : 'N/A' }}
                        </small>
                    </div>
                    <div class="grid grid-cols-4 gap-1.5 text-center text-xs font-semibold sm:w-80">
                        <span class="rounded-full bg-green-100 px-2 py-1 text-green-700">{{ $total->days_present }} Present</span>
                        <span class="rounded-full bg-red-100 px-2 py-1 text-red-700">{{ $total->days_absent }} Absent</span>
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">{{ $total->days_late }} Late</span>
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-blue-700">{{ $total->days_excused }} Excused</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
