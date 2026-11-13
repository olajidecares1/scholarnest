{{-- The last step of the school's own signup wizard, and only that.

     A Super Admin who opens a subscription is redirected to the review screen
     in their own panel before reaching here (see ConfirmationController), so
     this page has one audience again: the school that just paid. --}}
<x-dashboard-layout page-title="Payment Confirmed & Sent" page-subtitle="Your payment has been received and is under review.">
    <div class="wz-shell">
        <x-subscription-steps step="confirmation" :per-student="$subscription->billing_cycle === \App\Enums\BillingCycle::PerStudentPerTerm" />

        @if (session('status'))
            <div class="wz-note wz-note-info" role="status">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="wz-card">
            <div class="text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-[6px] ring-emerald-50/60 dark:bg-emerald-900/30 dark:ring-emerald-900/20">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>

                <p class="wz-eyebrow mt-4">Thank You!</p>
                <h2 class="mt-1 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl dark:text-white">Your Payment Has Been Received</h2>
                <small class="mx-auto mt-1.5 block max-w-md text-[12.5px] leading-[1.6] text-slate-500 dark:text-gray-400">
                    We have successfully received your payment receipt.
                    Our team will verify your payment and activate your subscription shortly.
                </small>
            </div>

            <div class="wz-note wz-note-success mt-5">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                <div>
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-emerald-900 dark:text-emerald-100">
                        Payment Status
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-800/60 dark:text-emerald-100">Received</span>
                    </p>
                    <small class="mt-0.5 block text-[11.5px]">Your payment is under review and will be confirmed within 24 hours.</small>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="wz-section-title">Payment & Subscription Summary</h3>
                <dl class="wz-panel wz-rows mt-2.5">
                    <div class="wz-row">
                        <dt>Plan Selected</dt>
                        <dd>{{ $subscription->plan->name }}</dd>
                    </div>
                    <div class="wz-row">
                        <dt>Billing Cycle</dt>
                        <dd>{{ $subscription->billing_cycle->label() }}</dd>
                    </div>
                    <div class="wz-row">
                        <dt>Amount Paid</dt>
                        <dd class="tabular-nums">&#8358;{{ number_format($subscription->amount, 2) }}</dd>
                    </div>
                    <div class="wz-row">
                        <dt>Reference</dt>
                        <dd class="font-semibold !text-primary-600 select-all">{{ $subscription->reference }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-gray-700">
                    <p class="wz-section-title">What Happens Next?</p>
                    <ul class="mt-2 space-y-1.5 text-[11.5px] leading-[1.5] text-slate-600 dark:text-gray-300">
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> We will verify your payment receipt</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> You will receive an email confirmation once verified</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> Your subscription will be activated</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> You can then set up your school website and manage your platform</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-gray-700">
                    @php($supportEmail = \App\Models\Setting::supportEmail())

                    <p class="wz-section-title">Need Help?</p>
                    <small class="mt-2 block text-[11.5px] leading-[1.5] text-slate-600 dark:text-gray-300">If you have any questions, our support team is here to help you.</small>

                    {{-- This button shipped as href="#". Somebody who has just
                         paid and wants to ask about it deserves a real
                         address, not a link that does nothing. --}}
                    @if ($supportEmail)
                        <a href="mailto:{{ $supportEmail }}" class="btn border wz-btn-quiet mt-3"><i class="fa-solid fa-envelope btn-icon" aria-hidden="true"></i> Contact Support</a>
                        <small class="mt-2 block break-all text-[11px] text-slate-400">{{ $supportEmail }}</small>
                    @endif
                </div>
            </div>

            <div class="wz-actions justify-center">
                <a href="{{ route('dashboard') }}" class="btn wz-btn">
                    <i class="fa-solid fa-arrow-left btn-icon" aria-hidden="true"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-dashboard-layout>
