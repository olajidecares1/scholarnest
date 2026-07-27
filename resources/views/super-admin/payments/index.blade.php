<x-super-admin-layout page-title="Payments" page-subtitle="Every payment submitted across the platform.">
    <div class="space-y-6">
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
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
                        class="w-full rounded-[5px] border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 lg:rounded-[10px]"
                    >
                </div>

                <select name="status" class="rounded-[5px] border-gray-300 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 lg:rounded-[10px]">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="verified" @selected(request('status') === 'verified')>Verified</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>

                <select name="method" class="rounded-[5px] border-gray-300 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 lg:rounded-[10px]">
                    <option value="">All Methods</option>
                    <option value="bank_transfer" @selected(request('method') === 'bank_transfer')>Bank Transfer</option>
                    <option value="paystack" @selected(request('method') === 'paystack')>Paystack</option>
                </select>

                <button type="submit" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 lg:rounded-[10px]">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Method</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-900">{{ $payment->subscription->school->name }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $payment->reference }}</td>
                                <td class="px-5 py-3 font-medium text-gray-900">&#8358;{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $payment->method->label() }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $payment->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $colors = ['pending' => 'bg-amber-100 text-amber-700', 'verified' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700'];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $colors[$payment->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $payment->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-gray-100 px-5 py-4">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
