<x-dashboard-layout page-title="Profile Change Requests" page-subtitle="Review requests from students, staff, and parents/guardians to correct protected identity fields.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="status" onchange="this.form.submit()" >
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Requester</th>
                            <th class="px-5 py-3 font-semibold">Field</th>
                            <th class="px-5 py-3 font-semibold">Current &rarr; Requested</th>
                            <th class="px-5 py-3 font-semibold">Reason</th>
                            <th class="px-5 py-3 font-semibold">Submitted</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($requests as $changeRequest)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ ucfirst($changeRequest->requester_type) }}</span>
                                    @if ($changeRequest->requester_type === 'guardian')
                                        <small class="block text-xs text-gray-400">for {{ ucfirst($changeRequest->subject_type) }}</small>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $changeRequest->field_label }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                    <span class="line-through opacity-60">{{ $changeRequest->current_value ?: 'N/A' }}</span>
                                    &rarr;
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $changeRequest->requested_value }}</span>
                                </td>
                                <td class="max-w-xs truncate px-5 py-3 text-gray-500 dark:text-gray-400">{{ $changeRequest->reason ?: 'N/A' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $changeRequest->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $changeRequest->status === 'pending',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $changeRequest->status === 'approved',
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $changeRequest->status === 'rejected',
                                    ])>{{ ucfirst($changeRequest->status) }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($changeRequest->status === 'pending')
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('profile-change-requests.approve', $changeRequest) }}">
                                                @csrf
                                                <button type="submit" class="text-xs font-semibold text-green-600 hover:text-green-700">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('profile-change-requests.reject', $changeRequest) }}">
                                                @csrf
                                                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Reject</button>
                                            </form>
                                        </div>
                                    @else
                                        <small class="block text-right text-xs text-gray-400">
                                            {{ $changeRequest->reviewedBy?->name ?? 'School Admin' }}
                                            &middot; {{ $changeRequest->reviewed_at?->format('M j, Y') }}
                                        </small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No change requests have been submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
