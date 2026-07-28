<x-super-admin-layout page-title="Payments" page-subtitle="Every payment submitted across the platform.">
    <div class="space-y-6">
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.payments.index') }}" class="flex flex-wrap items-center gap-3 p-4">
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
                        placeholder="Search schools..."
                        class="w-full rounded-[8px] border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>

                <div class="w-full sm:w-44">
                    <x-select-field
                        name="status"
                        :options="['' => 'All Statuses', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']"
                        :selected="request('status')"
                    />
                </div>

                <div class="w-full sm:w-48">
                    <x-select-field
                        name="method"
                        :options="['' => 'All Methods', 'bank_transfer' => 'Bank Transfer', 'paystack' => 'Paystack']"
                        :selected="request('method')"
                    />
                </div>

                <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Method</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($payments as $payment)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $payment->subscription->school->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $payment->reference }}</td>
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">&#8358;{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $payment->method->label() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $payment->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $colors = ['pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'verified' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $colors[$payment->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $payment->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
