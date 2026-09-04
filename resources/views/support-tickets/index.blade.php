<x-dashboard-layout page-title="Support Tickets" page-subtitle="Get help from the ScholarNest team.">
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <a
                href="{{ route('support-tickets.create') }}"
                class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-600"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                New Ticket
            </a>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Subject</th>
                            <th class="px-5 py-3 font-semibold">Priority</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Opened</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tickets as $ticket)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('support-tickets.show', $ticket) }}" class="font-semibold text-gray-900 hover:text-primary-500">{{ $ticket->subject }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $ticket->priority->label() }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusColors = ['open' => 'bg-amber-100 text-amber-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'resolved' => 'bg-green-100 text-green-700', 'closed' => 'bg-gray-200 text-gray-600'];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-600' }}">{{ $ticket->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $ticket->created_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">No support tickets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tickets->hasPages())
                <div class="border-t border-gray-100 px-5 py-4">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
