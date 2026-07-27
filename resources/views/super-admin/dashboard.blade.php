<x-super-admin-layout page-title="Dashboard" page-subtitle="Platform overview and recent activity." :pending-approvals-count="$stats['pendingApprovals']">
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['label' => 'Total Schools', 'value' => $stats['totalSchools'], 'color' => 'primary'],
                ['label' => 'Active Schools', 'value' => $stats['activeSchools'], 'color' => 'green'],
                ['label' => 'Pending Approvals', 'value' => $stats['pendingApprovals'], 'color' => 'amber'],
                ['label' => 'Active Subscriptions', 'value' => $stats['activeSubscriptions'], 'color' => 'green'],
                ['label' => 'Total School Admins', 'value' => $stats['totalUsers'], 'color' => 'primary'],
                ['label' => 'Expiring Soon (30 Days)', 'value' => $stats['expiringSoon'], 'color' => 'purple'],
            ] as $card)
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm lg:rounded-[10px]">
                    <p class="text-sm font-medium text-gray-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($card['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-bold text-gray-900">Recent Subscriptions</h2>
                <a href="{{ route('super-admin.subscriptions.index', ['tab' => 'all']) }}" class="text-sm font-semibold text-primary-500 hover:text-primary-600">View all &rarr;</a>
            </div>

            @if ($recentSubscriptions->isEmpty())
                <p class="p-6 text-center text-sm text-gray-500">No subscriptions yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">School</th>
                                <th class="px-5 py-3 font-semibold">Plan</th>
                                <th class="px-5 py-3 font-semibold">Amount</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recentSubscriptions as $subscription)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900">{{ $subscription->school->name }}</td>
                                    <td class="px-5 py-3 text-gray-600">{{ $subscription->plan->name }}</td>
                                    <td class="px-5 py-3 text-gray-600">&#8358;{{ number_format($subscription->amount, 2) }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ $subscription->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
