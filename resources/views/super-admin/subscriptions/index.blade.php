@php
    $tabs = [
        'pending' => 'Pending Approvals',
        'active' => 'Active Subscriptions',
        'expired' => 'Expired Subscriptions',
        'all' => 'All Subscriptions',
        'top-ups' => 'Top-Up Requests'.($stats['pendingTopUps'] > 0 ? " ({$stats['pendingTopUps']})" : ''),
    ];
@endphp

<x-super-admin-layout page-title="Subscription Approvals" page-subtitle="Review and manage schools subscription requests.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @include('super-admin.subscriptions._email-outcome')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Pending Approvals</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['pending']) }}</p>
                <small class="field-hint">Schools awaiting approval</small>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Auto-Activate Eligible</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['autoActivate']) }}</p>
                <small class="field-hint">Returning schools, fast-track review</small>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-green-600 dark:text-green-400">Active Subscriptions</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['active']) }}</p>
                <small class="field-hint">Currently active schools</small>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Expiring Soon (30 Days)</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($stats['expiringSoon']) }}</p>
                <small class="field-hint">Subscriptions expiring soon</small>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]" x-data="{ selectedIds: [], rejectingBulk: false, bulkReason: '' }">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 pt-2 dark:border-gray-700">
                <div class="flex flex-wrap gap-1">
                    @foreach ($tabs as $key => $label)
                        <a
                            href="{{ route('super-admin.subscriptions.index', ['tab' => $key]) }}"
                            class="rounded-t-[5px] border-b-2 px-4 py-3 text-sm font-semibold transition-colors duration-200 {{ $tab === $key ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @if ($tab !== 'top-ups')
                    <a
                        href="{{ route('super-admin.subscriptions.export', request()->query()) }}"
                        class="btn mb-2 flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3M12 4v12m0-12l-4 4m4-4l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Export
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('super-admin.subscriptions.index') }}" class="flex flex-wrap items-center gap-3 p-4">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="relative flex-1 min-w-[200px]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa-solid fa-magnifying-glass text-[14px] leading-none" aria-hidden="true"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search schools..."
                        class="w-full pl-9"
                    >
                </div>

                <div class="w-full sm:w-40">
                    <x-select-field
                        name="plan_id"
                        :options="collect(['' => 'All Plans'])->union($plans->pluck('name', 'id'))"
                        :selected="request('plan_id')"
                    />
                </div>

                <div class="w-full sm:w-48">
                    <x-select-field
                        name="billing_cycle"
                        :options="[
                            '' => 'All Billing Cycles',
                            'monthly' => 'Monthly',
                            'per_term' => 'Per Term',
                            'per_student_per_term' => 'Per Student / Per Term',
                        ]"
                        :selected="request('billing_cycle')"
                    />
                </div>

                <div class="w-full sm:w-48">
                    <x-select-field
                        name="payment_method"
                        :options="[
                            '' => 'All Payment Methods',
                            'bank_transfer' => 'Bank Transfer',
                            'paystack' => 'Paystack',
                        ]"
                        :selected="request('payment_method')"
                    />
                </div>

                <button
                    type="submit"
                    class="btn flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="fa-solid fa-filter text-[14px] leading-none" aria-hidden="true"></i>
                    Filter
                </button>
            </form>

            <div x-show="selectedIds.length > 0" style="display: none;" class="flex items-center justify-between gap-3 border-b border-gray-100 bg-primary-50 px-4 py-3 dark:border-gray-700 dark:bg-primary-900/20">
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                    <span x-text="selectedIds.length"></span> selected
                </p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="$refs.bulkApproveForm.submit()" class="btn rounded-[8px] bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                        Approve Selected
                    </button>
                    <button type="button" @click="rejectingBulk = true" class="btn rounded-[8px] bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                        Reject Selected
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('super-admin.subscriptions.bulk-approve') }}" x-ref="bulkApproveForm">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="subscription_ids[]" :value="id">
                </template>
            </form>
            <form method="POST" action="{{ route('super-admin.subscriptions.bulk-reject') }}" x-ref="bulkRejectForm">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="subscription_ids[]" :value="id">
                </template>
                <input type="hidden" name="reason" :value="bulkReason">
            </form>

            <div x-show="rejectingBulk" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="rejectingBulk = false" class="w-full max-w-md rounded-[8px] bg-white p-5 dark:bg-gray-800">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Reject <span x-text="selectedIds.length"></span> selected subscription(s)?</h3>
                    <div class="mt-3">
                        <x-textarea-field
                            name="bulk_reason_display"
                            x-model="bulkReason"
                            rows="3"
                            placeholder="Reason (optional, sent to each school)"
                        />
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" @click="rejectingBulk = false" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="button" @click="$refs.bulkRejectForm.submit()" class="btn rounded-[8px] bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Reject Subscriptions</button>
                    </div>
                </div>
            </div>

            @if ($tab === 'top-ups')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-3 font-semibold">School</th>
                                <th class="px-5 py-3 font-semibold">Additional Slots</th>
                                <th class="px-5 py-3 font-semibold">Additional Amount</th>
                                <th class="px-5 py-3 font-semibold">Requested On</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($topUps as $topUp)
                                <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50" x-data="{ rejecting: false }">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $topUp->subscription->school->name }}</p>
                                        <small class="field-hint">{{ $topUp->subscription->plan->name }} &middot; {{ $topUp->reference }}</small>
                                    </td>
                                    <td class="px-5 py-3">
                                        {{-- What the school ASKED for. What is actually
                                             allocated is entered by the Super Admin below. --}}
                                        <p class="font-medium text-gray-900 dark:text-white">+{{ number_format($topUp->additional_students_count) }} requested</p>
                                        @if ($topUp->approved_students_count !== null)
                                            <p class="text-xs font-semibold text-green-700 dark:text-green-400">
                                                +{{ number_format($topUp->approved_students_count) }} allocated
                                                ({{ number_format($topUp->previous_students_count) }} &rarr; {{ number_format($topUp->new_students_count) }})
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-900 dark:text-white">&#8358;{{ number_format($topUp->additional_amount, 2) }}</p>
                                        @if ($topUp->price_per_student !== null)
                                            <small class="field-hint">&#8358;{{ number_format($topUp->price_per_student, 2) }} per student</small>
                                        @endif
                                        @if (filled($topUp->receipt_path))
                                            <a
                                                href="{{ route('super-admin.subscriptions.top-ups.receipt', $topUp) }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700"
                                            >
                                                <i class="fa-solid fa-receipt"></i> View receipt
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $topUp->created_at->format('M j, Y') }}</td>
                                    <td class="px-5 py-3">
                                        @php
                                            $topUpStatusColors = [
                                                'pending_verification' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                            ];
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $topUpStatusColors[$topUp->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $topUp->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($topUp->status->value === 'pending_verification')
                                            {{-- The number allocated is entered here, not
                                                 taken from the request. Check the receipt
                                                 against the amount paid, then enter the
                                                 licences that amount actually covers, it
                                                 may not be what was asked for. --}}
                                            <form method="POST" action="{{ route('super-admin.subscriptions.top-ups.approve', $topUp) }}" class="flex flex-wrap items-end gap-2">
                                                @csrf
                                                <div>
                                                    <label for="approved-{{ $topUp->id }}" class="mb-1 block text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                                                        Licences to allocate
                                                    </label>
                                                    <input
                                                        id="approved-{{ $topUp->id }}"
                                                        type="number"
                                                        name="approved_students_count"
                                                        min="1"
                                                        max="100000"
                                                        required
                                                        placeholder="{{ $topUp->additional_students_count }}"
                                                        class="w-28"
                                                    >
                                                </div>
                                                <button type="submit" class="btn h-8 rounded-[8px] bg-green-600 px-3 text-xs font-semibold text-white hover:bg-green-700">Approve</button>
                                                <button type="button" @click="rejecting = true" class="btn h-8 rounded-[8px] bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700">Reject</button>
                                            </form>

                                            @error('approved_students_count')
                                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                            @enderror

                                            <div x-show="rejecting" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                                                <div @click.outside="rejecting = false" class="w-full max-w-md rounded-[8px] bg-white p-5 dark:bg-gray-800">
                                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Reject top-up for {{ $topUp->subscription->school->name }}?</h3>
                                                    <form method="POST" action="{{ route('super-admin.subscriptions.top-ups.reject', $topUp) }}" class="mt-3">
                                                        @csrf
                                                        <x-textarea-field
                                                            name="reason"
                                                            rows="3"
                                                            placeholder="Reason (optional, sent to the school)"
                                                        />
                                                        <div class="mt-3 flex justify-end gap-2">
                                                            <button type="button" @click="rejecting = false" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                                                            <button type="submit" class="btn rounded-[8px] bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Reject Top-Up</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @elseif ($topUp->status->value === 'approved')
                                            {{-- The top-up confirmation and its invoice, sent again to
                                                 whoever has not received them. --}}
                                            <form method="POST" action="{{ route('super-admin.subscriptions.top-ups.resend-emails', $topUp) }}" x-data="{ sending: false }" @submit="sending = true">
                                                @csrf
                                                <button type="submit" :disabled="sending" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                                    <span x-show="! sending">Resend emails</span>
                                                    <span x-show="sending" x-cloak>Sending&hellip;</span>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No top-up requests found for this view.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($topUps->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                        {{ $topUps->links() }}
                    </div>
                @endif
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-3">
                                @if ($tab === 'pending')
                                    <input
                                        type="checkbox"
                                        @change="$event.target.checked ? selectedIds = {{ $subscriptions->pluck('id')->values()->toJson() }} : selectedIds = []"
                                        class="text-primary-500"
                                    >
                                @endif
                            </th>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Plan &amp; Billing</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Payment Method</th>
                            <th class="px-5 py-3 font-semibold">Requested On</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($subscriptions as $subscription)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3">
                                    @if ($subscription->status->value === 'pending_verification')
                                        <input
                                            type="checkbox"
                                            value="{{ $subscription->id }}"
                                            :checked="selectedIds.includes({{ $subscription->id }})"
                                            @change="$event.target.checked ? selectedIds.push({{ $subscription->id }}) : selectedIds = selectedIds.filter(id => id !== {{ $subscription->id }})"
                                            class="text-primary-500"
                                        >
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $subscription->school->name }}</p>
                                    @if ($autoActivateEligibleIds->contains($subscription->id))
                                        <span class="mt-0.5 inline-block rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">Auto-Activate Eligible</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $subscription->plan->name }}</span>
                                    <small class="field-hint mt-1">{{ $subscription->billing_cycle->label() }}</small>
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">&#8358;{{ number_format($subscription->amount, 2) }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $subscription->latestPayment?->method?->label() ?? 'N/A' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $subscription->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusColors = [
                                            'pending_verification' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                            'active' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                            'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                            'expired' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                            'pending_payment' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
                                        ];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors[$subscription->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $subscription->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2" x-data="{ open: false, rejecting: false }">
                                        <a
                                            href="{{ route('super-admin.subscriptions.show', $subscription) }}"
                                            class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            View
                                        </a>

                                        @if ($subscription->status->value === 'pending_verification')
                                            <div class="relative">
                                                <button type="button" @click="open = !open" @click.outside="open = false" class="flex h-8 w-8 items-center justify-center rounded-[8px] border border-gray-300 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                                                    <i class="fa-solid fa-ellipsis-vertical text-[14px] leading-none" aria-hidden="true"></i>
                                                </button>

                                                <div x-show="open" x-transition style="display: none;" class="absolute right-0 z-10 mt-1 w-56 rounded-[5px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                                                    <form method="POST" action="{{ route('super-admin.subscriptions.approve', $subscription) }}">
                                                        @csrf
                                                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-gray-700">
                                                            Approve &amp; Activate
                                                        </button>
                                                    </form>
                                                    <button type="button" @click="rejecting = true; open = false" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-gray-700">
                                                        Reject
                                                    </button>
                                                </div>
                                            </div>

                                            <div x-show="rejecting" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                                                <div @click.outside="rejecting = false" class="w-full max-w-md rounded-[8px] bg-white p-5 dark:bg-gray-800">
                                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Reject subscription for {{ $subscription->school->name }}?</h3>
                                                    <form method="POST" action="{{ route('super-admin.subscriptions.reject', $subscription) }}" class="mt-3">
                                                        @csrf
                                                        <x-textarea-field
                                                            name="reason"
                                                            rows="3"
                                                            placeholder="Reason (optional, sent to the school)"
                                                        />
                                                        <div class="mt-3 flex justify-end gap-2">
                                                            <button type="button" @click="rejecting = false" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                                                            <button type="submit" class="btn rounded-[8px] bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Reject Subscription</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No subscriptions found for this view.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($subscriptions->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $subscriptions->links() }}
                </div>
            @endif
            @endif
        </div>
    </div>
</x-super-admin-layout>
