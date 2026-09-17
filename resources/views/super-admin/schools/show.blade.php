<x-super-admin-layout :page-title="$school->name" page-subtitle="School profile, billing, and subscription history.">
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $school->name }}</h2>
                    @if ($school->is_active)
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                    @else
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Inactive</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Joined {{ $school->created_at->format('F j, Y') }}</p>
            </div>

            @if ($school->is_active)
                <form method="POST" action="{{ route('super-admin.schools.deactivate', $school) }}" onsubmit="return confirm('Deactivate {{ $school->name }}? Their admins will lose access immediately.');">
                    @csrf
                    <button type="submit" class="rounded-[8px] border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Deactivate School</button>
                </form>
            @else
                <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}">
                    @csrf
                    <button type="submit" class="rounded-[8px] border border-green-300 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/20">Activate School</button>
                </form>
            @endif
        </div>

        @include('super-admin.schools.partials.website-address')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Billing Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Contact Name</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_contact_name ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Billing Email</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_email ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_phone ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-gray-900 dark:text-white">{{ $school->billing_address ?? 'N/A' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">School Admins</h3>
                <ul class="mt-3 space-y-3">
                    @forelse ($school->users as $user)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                {{ Str::of($user->name)->substr(0, 1)->upper() }}
                            </span>
                            <span>
                                <span class="block font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">No admins found.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- Student capacity.
             One cumulative figure per school, however many times it has been
             topped up. The table below is the history of how it got there,
             each row records what the figure moved FROM and TO, but the
             school only ever draws on the single total. --}}
        @if ($capacity)
            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Student Capacity</h3>
                    <p class="field-hint mt-0.5">
                        Billed per student. This is the ceiling the school is held to when admitting students.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 lg:grid-cols-4 dark:bg-gray-700">
                    @foreach ([
                        ['Initial allocation', number_format($capacity['initial']), 'text-gray-600 dark:text-gray-300'],
                        ['Total approved', number_format($capacity['allocated']), 'text-gray-900 dark:text-white'],
                        ['Registered', number_format($capacity['used']), 'text-gray-900 dark:text-white'],
                        ['Remaining', number_format($capacity['remaining']), $capacity['remaining'] === 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'],
                    ] as [$label, $value, $tone])
                        <div class="bg-white p-4 dark:bg-gray-800">
                            <p class="field-hint">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold {{ $tone }}">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($capacity['remaining'] === 0)
                    <div class="border-t border-gray-100 bg-amber-50 p-4 text-xs font-medium text-amber-800 dark:border-gray-700 dark:bg-amber-900/20 dark:text-amber-300">
                        This school has used every approved space. It cannot admit another student until additional capacity is approved.
                    </div>
                @endif

                <div class="border-t border-gray-100 dark:border-gray-700">
                    <div class="p-5 pb-3">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Capacity history</h4>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Requested</th>
                                    <th class="px-5 py-3 font-semibold">Approved</th>
                                    <th class="px-5 py-3 font-semibold">Capacity</th>
                                    <th class="px-5 py-3 font-semibold">Amount</th>
                                    <th class="px-5 py-3 font-semibold">Status</th>
                                    <th class="px-5 py-3 font-semibold">Reviewed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($topUps as $topUp)
                                    <tr>
                                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300">+{{ number_format($topUp->additional_students_count) }}</td>
                                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">
                                            {{ $topUp->approved_students_count !== null ? '+'.number_format($topUp->approved_students_count) : 'N/A' }}
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">
                                            @if ($topUp->new_students_count !== null)
                                                {{ number_format($topUp->previous_students_count) }} &rarr; <span class="font-bold text-gray-900 dark:text-white">{{ number_format($topUp->new_students_count) }}</span>
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300">&#8358;{{ number_format((float) $topUp->additional_amount, 2) }}</td>
                                        <td class="px-5 py-3">
                                            @php($tone = match ($topUp->status) {
                                                \App\Enums\SubscriptionTopUpStatus::Approved => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                                \App\Enums\SubscriptionTopUpStatus::Rejected => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                                default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                            })
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ $topUp->status->label() }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $topUp->verified_at ? $topUp->verified_at->format('j M Y').' · '.($topUp->verifiedBy?->name ?? 'N/A') : 'Awaiting review' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No additional capacity has been requested. The school is on its initial allocation.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Subscription History</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Plan</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($school->subscriptions as $subscription)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $subscription->plan->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">&#8358;{{ number_format($subscription->amount, 2) }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('super-admin.subscriptions.show', $subscription) }}" class="text-primary-500 hover:text-primary-600">{{ $subscription->reference }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $subscription->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $subscription->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No subscriptions yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payment and invoice history.

             Deliberately one table rather than two. An invoice, the payment
             against it and the Super Admin who approved that payment are the
             same story, and splitting them across "invoices" and "payments"
             tabs makes answering "was this paid, and who signed it off?"
             a matter of cross-referencing two screens by date. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Payments &amp; Invoices</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Every invoice raised against this school, with its payment and approval.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Invoice</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Plan</th>
                            <th class="px-5 py-3 font-semibold">Licences</th>
                            <th class="px-5 py-3 font-semibold">Amount</th>
                            <th class="px-5 py-3 font-semibold">Reference</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Approved</th>
                            <th class="px-5 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $invoice->number }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->issued_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $invoice->plan_name }}
                                    @if ($invoice->subscription_top_up_id)
                                        <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:bg-gray-700 dark:text-gray-300">Top-up</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->licences ? number_format($invoice->licences) : 'N/A' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->formattedTotal() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $invoice->payment_reference ?: 'N/A' }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->statusBadgeClasses() }}">{{ $invoice->paymentStatus() }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">
                                    {{-- Who approved it, not just that somebody did. "Approved"
                                         with no name against it is not an audit trail. --}}
                                    @if ($invoice->approvedAt())
                                        {{ $invoice->approvedAt()->format('M j, Y') }}
                                        @if ($invoice->approvedBy())
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">by {{ $invoice->approvedBy()->name }}</span>
                                        @endif
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('invoices.download', $invoice) }}" class="text-primary-500 hover:text-primary-600">Download</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No invoices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <a href="{{ route('super-admin.schools.index') }}" class="inline-block text-sm font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">&larr; Back to Schools</a>
    </div>
</x-super-admin-layout>
