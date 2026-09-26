@php
    $classOptions = $academicLevels->flatMap->classes->pluck('name', 'name')->all();
    $methodCollection = collect($methodOptions)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all();
@endphp

<x-dashboard-layout page-title="Invoices" page-subtitle="Track student invoices and record payments.">
    <div class="space-y-6" x-data="{ addOpen: false, payOpen: false, paying: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <a href="{{ route('finance.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Fee Structures</a>
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Invoices</span>
            </div>

            <button
                type="button"
                @click="addOpen = true"
                class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                Add Invoice
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center gap-1">
                <p class="text-sm font-medium text-orange-600">Total Outstanding</p>
                <x-stat-tooltip text="Sum of every invoice's unpaid balance across all students, regardless of the filter above." />
            </div>
            <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">&#8358;{{ number_format($totalOutstanding, 2) }}</p>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div class="flex gap-1 rounded-[8px] border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-700/50">
                    @foreach (['' => 'All', 'unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid'] as $value => $label)
                        <a href="{{ route('finance.invoices.index', ['status' => $value]) }}" class="btn rounded-[6px] px-3 py-1.5 text-xs font-semibold transition-colors duration-150 {{ request('status', '') === $value ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search student..."
                        class="w-52"
                    >
                    <div class="w-40">
                        <x-select-field name="class" placeholder="All Classes" :selected="request('class')" :options="['' => 'All Classes'] + $classOptions" />
                    </div>
                    <button type="submit" class="btn h-10 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold">Amount</th>
                            <th class="px-6 py-3 font-semibold">Paid</th>
                            <th class="px-6 py-3 font-semibold">Balance</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($invoices as $invoice)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $invoice->student->fullName() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->title }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">&#8358;{{ number_format((float) $invoice->amount, 2) }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">&#8358;{{ number_format($invoice->amountPaid(), 2) }}</td>
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">&#8358;{{ number_format($invoice->balance(), 2) }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->statusBadgeClasses() }}">{{ $invoice->status() }}</span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($invoice->balance() > 0)
                                            <button
                                                type="button"
                                                @click="paying = @js(['uuid' => $invoice->uuid, 'balance' => $invoice->balance(), 'student' => $invoice->student->fullName()]); payOpen = true"
                                                class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                            >
                                                Record Payment
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('finance.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this invoice?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>

        {{-- Add Invoice modal --}}
        <div x-show="addOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addOpen = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Invoice</h3>
                <form method="POST" action="{{ route('finance.invoices.store') }}" class="mt-4 space-y-2">
                    @csrf
                    <x-select-field name="student_id" label="Student" required placeholder="Select a student" :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->fullName()])->all()" />
                    <x-text-field name="title" label="Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" placeholder="e.g. Exam Fee" required />
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="amount" label="Amount (₦)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0" step="0.01" required />
                        <x-text-field name="due_date" label="Due Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Optional." />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addOpen = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Invoice</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Record Payment modal --}}
        <div x-show="payOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="payOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Record Payment</h3>
                <small class="block mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="paying">
                    <span x-text="paying ? paying.student : ''"></span> &middot; Balance: &#8358;<span x-text="paying ? paying.balance.toLocaleString() : ''"></span>
                </small>
                <form
                    method="POST"
                    :action="paying ? '{{ route('finance.invoices.payments.store', ['invoice' => '__ID__']) }}'.replace('__ID__', paying.uuid) : '#'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <x-text-field name="amount" label="Amount Paid (₦)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0.01" step="0.01" required />
                    <x-text-field name="paid_at" label="Date Paid" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" :value="now()->toDateString()" required />
                    <x-select-field name="method" label="Method" required :options="$methodCollection" />
                    <x-text-field name="reference" label="Reference" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" helper="Optional." />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="payOpen = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
