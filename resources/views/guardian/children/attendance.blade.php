<x-guardian-layout page-title="Attendance" :page-subtitle="$student->fullName().'\'s attendance history'" :active-child="$activeChild">
    <div class="space-y-6">
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-50 text-lg font-extrabold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $monthPercent }}%</span>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">This Month's Attendance</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Present {{ $monthPresent }} of {{ $monthTotal }} recorded days</p>
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($records as $record)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <span class="text-sm text-gray-700 dark:text-gray-200">{{ $record->date->format('l, M j, Y') }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $record->status->badgeClasses() }}">{{ $record->status->label() }}</span>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No attendance recorded yet.</p>
                @endforelse
            </div>
        </div>

        <div>{{ $records->links() }}</div>
    </div>
</x-guardian-layout>
