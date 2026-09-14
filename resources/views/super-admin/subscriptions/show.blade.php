@php
    $payment = $subscription->latestPayment;
    $school = $subscription->school;
    $isPending = $subscription->status === \App\Enums\SubscriptionStatus::PendingVerification;

    $statusBadge = match ($subscription->status) {
        \App\Enums\SubscriptionStatus::Active => ['Active', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
        \App\Enums\SubscriptionStatus::Rejected => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
        \App\Enums\SubscriptionStatus::Expired => ['Expired', 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
        default => ['Awaiting your review', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
    };
@endphp

<x-super-admin-layout
    :page-title="$school->name"
    page-subtitle="Review this school's payment and decide whether to activate its subscription."
>
    <div class="mx-auto max-w-4xl space-y-6" x-data="{ rejecting: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('super-admin.subscriptions.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                &larr; Back to subscriptions
            </a>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
        </div>

        {{-- What was submitted --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Subscription</h2>
            </div>

            <dl class="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2 dark:bg-gray-700">
                @foreach ([
                    ['School', $school->name],
                    ['Plan', $subscription->plan?->name ?? 'N/A'],
                    ['Billing cycle', $subscription->billing_cycle?->label() ?? 'N/A'],
                    ['Student licences', $subscription->students_count ?? 'N/A'],
                    ['Amount', '₦'.number_format((float) $subscription->amount, 2)],
                    ['Reference', $subscription->reference],
                    ['Submitted', $subscription->created_at?->format('j M Y, g:ia') ?? 'N/A'],
                    ['Starts / ends', $subscription->starts_at ? $subscription->starts_at->format('j M Y').' to '.($subscription->ends_at?->format('j M Y') ?? 'N/A') : 'Not started'],
                ] as [$label, $value])
                    <div class="bg-white p-4 dark:bg-gray-800">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- The payment itself. Without this the Super Admin was being asked to
             "verify a payment" they had no way of looking at. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Payment</h2>
            </div>

            <div class="space-y-4 p-5">
                @if ($payment)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <p class="field-hint">Method</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $payment->method?->label() ?? $payment->method ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="field-hint">Status</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $payment->status?->label() ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="field-hint">Verified</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $payment->verified_at ? $payment->verified_at->format('j M Y, g:ia').' by '.($payment->verifiedBy?->name ?? 'unknown') : 'Not yet' }}
                            </p>
                        </div>
                    </div>

                    @if ($payment->receipt_path)
                        <a
                            href="{{ route('super-admin.subscriptions.receipt', $subscription) }}"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5" />
                                <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            View uploaded receipt
                        </a>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">No receipt was uploaded with this payment.</p>
                    @endif

                    @if ($payment->notes)
                        <div class="rounded-[8px] bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                            <span class="font-semibold">Notes:</span> {{ $payment->notes }}
                        </div>
                    @endif
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No payment has been recorded against this subscription.</p>
                @endif
            </div>
        </div>

        {{-- The decision, made here rather than anywhere else. --}}
        @if ($isPending)
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Decision</h2>
                <p class="field-hint mt-0.5">
                    Activating grants {{ $school->name }} its plan immediately. Nothing else activates a school.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('super-admin.subscriptions.approve', $subscription) }}" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="rounded-[8px] bg-green-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-green-600/30 transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span x-show="! submitting">Activate subscription</span>
                            <span x-show="submitting" x-cloak>Activating&hellip;</span>
                        </button>
                    </form>

                    <button
                        type="button"
                        @click="rejecting = ! rejecting"
                        class="rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Reject
                    </button>
                </div>

                <form
                    x-show="rejecting"
                    x-cloak
                    method="POST"
                    action="{{ route('super-admin.subscriptions.reject', $subscription) }}"
                    class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700"
                    x-data="{ submitting: false }"
                    @submit="submitting = true"
                >
                    @csrf
                    <label for="reason" class="field-label">
                        Why is this being rejected? The school is shown this.
                    </label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        required
                        class="mt-1 w-full"
                    ></textarea>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="mt-3 rounded-[8px] bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-red-600/30 transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="! submitting">Reject subscription</span>
                        <span x-show="submitting" x-cloak>Rejecting&hellip;</span>
                    </button>
                </form>
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('super-admin.schools.show', $school) }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                View school
            </a>
        </div>
    </div>
</x-super-admin-layout>
