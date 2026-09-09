<x-dashboard-layout page-title="Invoices" page-subtitle="Your AkademicNest subscription invoices and their payment status.">
    <div class="space-y-6">
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Billing History</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Every invoice AkademicNest has issued to your school. Download any of them as a PDF.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Invoice</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Description</th>
                            <th class="px-5 py-3 font-semibold">Licences</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $invoice->number }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->issued_at->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->description }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->licences ? number_format($invoice->licences) : '—' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->formattedTotal() }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $invoice->statusBadgeClasses() }}">{{ $invoice->paymentStatus() }}</span>

                                    {{-- Said plainly rather than left to be inferred from an
                                         amber badge: a school waiting on approval needs to know
                                         that nothing more is required of them. --}}
                                    @unless ($invoice->isPaid())
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Awaiting AkademicNest approval</span>
                                    @endunless
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('invoices.download', $invoice) }}" class="font-semibold text-primary-500 hover:text-primary-600">Download</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    You have no invoices yet. One is issued as soon as you submit a subscription.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
