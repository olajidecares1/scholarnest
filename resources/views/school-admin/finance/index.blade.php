@php
    $classOptions = $academicLevels->flatMap->classes->pluck('name', 'name')->all();
@endphp

<x-dashboard-layout page-title="Finance" page-subtitle="Manage fee structures, invoices, and payments.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Fee Structures</span>
                <a href="{{ route('finance.invoices.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Invoices</a>
            </div>

            <button
                type="button"
                @click="open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                New Fee Structure
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Fee Structures</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Define a fee once, then generate an invoice for every matching student.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Name</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Session / Term</th>
                            <th class="px-6 py-3 font-semibold">Amount</th>
                            <th class="px-6 py-3 font-semibold">Invoices Generated</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($structures as $structure)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $structure->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $structure->class_name ?? 'All Classes' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $structure->session }} @if ($structure->term) &middot; {{ $structure->term }} @endif</td>
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">&#8358;{{ number_format((float) $structure->amount, 2) }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $structure->invoices_count }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('finance.generate', $structure) }}" onsubmit="return confirm('Generate invoices for every matching active student who doesn\'t already have one?');">
                                            @csrf
                                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Generate Invoices</button>
                                        </form>
                                        <form method="POST" action="{{ route('finance.destroy', $structure) }}" onsubmit="return confirm('Delete {{ $structure->name }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No fee structures yet. Click "New Fee Structure" to define one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- New Fee Structure modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">New Fee Structure</h3>
                <form method="POST" action="{{ route('finance.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <x-text-field name="name" label="Name" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" placeholder="e.g. Tuition Fee" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field name="class_name" label="Class" placeholder="All Classes" :options="$classOptions" helper="Leave unselected to apply to every class." />
                        <x-text-field name="amount" label="Amount (₦)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0" step="0.01" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="session" label="Session" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" placeholder="e.g. 2025/2026" required />
                        <x-text-field name="term" label="Term" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" placeholder="e.g. First Term" helper="Optional." />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Fee Structure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
