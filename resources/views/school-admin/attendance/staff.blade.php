<x-dashboard-layout page-title="Staff Register" page-subtitle="When each member of staff arrived, and when they left.">
    <div class="space-y-6">
        @include('school-admin.attendance._tabs', ['current' => 'staff'])

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search staff name..." class="w-56">
                    <input type="date" name="from" value="{{ request('from') }}" class="w-44">
                    <input type="date" name="to" value="{{ request('to') }}" class="w-44">
                    <x-primary-button>Filter</x-primary-button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Staff</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Arrived</th>
                            <th class="px-6 py-3">Left</th>
                            <th class="px-6 py-3">On site</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($records as $record)
                            @php $minutes = $record->minutesOnSite(); @endphp
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">{{ $record->staff?->first_name }} {{ $record->staff?->last_name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->date->format('D j M Y') }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->arrived_at?->format('g:i:sa') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->departed_at?->format('g:i:sa') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $minutes === null ? '—' : intdiv($minutes, 60).'h '.($minutes % 60).'m' }}
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $record->status->badgeClasses() }}">{{ $record->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No staff attendance recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">{{ $records->links() }}</div>
        </div>
    </div>
</x-dashboard-layout>
