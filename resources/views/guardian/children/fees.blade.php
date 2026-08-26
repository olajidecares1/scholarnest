<x-guardian-layout page-title="Fees & Payments" :page-subtitle="$student->fullName().'\'s invoices and payment history'" :active-child="$activeChild">
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Outstanding</p>
                <p class="mt-1 text-2xl font-extrabold text-red-600 dark:text-red-400">₦{{ number_format($totalOutstanding, 2) }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Total Paid</p>
                <p class="mt-1 text-2xl font-extrabold text-green-600 dark:text-green-400">₦{{ number_format($totalPaid, 2) }}</p>
            </div>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($invoices as $invoice)
                    <div class="p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $invoice->title }}</p>
                                <p class="field-hint">Due {{ $invoice->due_date->format('M j, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">₦{{ number_format($invoice->amount, 2) }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice->statusBadgeClasses() }}">{{ $invoice->status() }}</span>
                            </div>
                        </div>
                        @if ($invoice->balance() > 0)
                            <p class="field-hint mt-2">Balance: ₦{{ number_format($invoice->balance(), 2) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">No invoices yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-guardian-layout>
