<x-super-admin-layout page-title="Support Tickets" page-subtitle="Track and resolve support requests from schools.">
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Open</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['open']) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-400">In Progress</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['inProgress']) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-green-600 dark:text-green-400">Resolved</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['resolved']) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.support-tickets.index') }}" class="flex flex-wrap items-center gap-3 p-4">
                <div class="relative flex-1 min-w-[200px]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search subject or school..."
                        class="w-full pl-9"
                    >
                </div>

                <div class="w-full sm:w-44">
                    <x-select-field
                        name="status"
                        :options="[
                            '' => 'All Statuses',
                            'open' => 'Open',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ]"
                        :selected="request('status')"
                    />
                </div>

                <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Subject</th>
                            <th class="px-5 py-3 font-semibold">Priority</th>
                            <th class="px-5 py-3 font-semibold">Assigned To</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Opened</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($tickets as $ticket)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $ticket->school->name }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('super-admin.support-tickets.show', $ticket) }}" class="font-semibold text-gray-900 hover:text-primary-500 dark:text-white dark:hover:text-primary-400">{{ $ticket->subject }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $ticket->priority->label() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $ticket->assignedTo?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusColors = ['open' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', 'resolved' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'closed' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-600' }}">{{ $ticket->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $ticket->created_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No support tickets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tickets->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
