<x-dashboard-layout page-title="Review & Confirm" page-subtitle="Review your subscription details before submitting.">
    <div class="wz-shell">
        <x-subscription-steps step="review" />

        <div class="wz-card">
            @if ($errors->has('subscription'))
                <div class="wz-note wz-note-error mb-5" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>{{ $errors->first('subscription') }}</span>
                </div>
            @endif

            <h2 class="wz-title">Subscription Summary</h2>
            <small class="wz-lead">Please review the details below before confirming.</small>

            <dl class="wz-panel wz-rows mt-5">
                <div class="wz-row">
                    <dt>Plan</dt>
                    <dd>{{ $plan->name }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Billing Cycle</dt>
                    <dd>{{ $billingCycle->label() }}</dd>
                </div>
                @if ($studentsCount)
                    {{-- The unit price is shown as well as the total so the
                         school can check the arithmetic itself rather than
                         being asked to trust a single figure. It comes from
                         the plan record the Super Admin configures, never a
                         number written into this page. --}}
                    <div class="wz-row">
                        <dt>Price per Student</dt>
                        <dd>&#8358;{{ number_format($plan->price_per_student_per_term, 2) }}</dd>
                    </div>
                    <div class="wz-row">
                        <dt>Students Requested</dt>
                        <dd>{{ number_format($studentsCount) }}</dd>
                    </div>
                @endif
                <div class="wz-row">
                    <dt>Billing Contact</dt>
                    <dd>{{ $billingContactName }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Billing Email</dt>
                    <dd>{{ $billingEmail }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Phone</dt>
                    <dd>{{ $billingPhone }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Address</dt>
                    <dd>{{ $billingAddress }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Payment Method</dt>
                    <dd>{{ $paymentMethod->label() }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Receipt</dt>
                    <dd>{{ $receiptOriginalName }}</dd>
                </div>
                <div class="wz-row">
                    <dt>Reference</dt>
                    <dd class="font-semibold !text-primary-600">{{ $reference }}</dd>
                </div>
            </dl>

            <div class="wz-total mt-3">
                <span class="wz-total-label">Total Amount</span>
                <span class="wz-total-value tabular-nums">&#8358;{{ number_format($amount, 2) }}</span>
            </div>

            {{-- Said before the school pays, not after. Submitting a receipt is
                 a request, not an activation: nothing opens until a Super Admin
                 has reviewed the payment. A school that learns this only from
                 the confirmation screen has already been surprised. --}}
            <div class="wz-note wz-note-warn mt-3">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div>
                    <p class="font-semibold text-amber-900 dark:text-amber-100">Activation Status: Awaiting AkademicNest Team Approval</p>
                    <p class="mt-0.5">
                        @if ($studentsCount)
                            Once your payment is verified, your school is activated with
                            <span class="font-semibold">{{ number_format($studentsCount) }}</span> student
                            {{ \Illuminate\Support\Str::plural('space', $studentsCount) }}. You can request more at any time.
                        @else
                            Your subscription becomes active once a AkademicNest Team has verified your payment.
                        @endif
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('subscriptions.review.store') }}" x-data="{ busy: false }" @pageshow.window="busy = false" @submit="if (busy) { $event.preventDefault(); return; } busy = true">
                @csrf

                <div class="wz-actions">
                    <a href="{{ route('subscriptions.payment-method') }}" class="wz-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>

                    {{-- Pressed once: a second press while the first is on its way
                         would only be turned away by the server. --}}
                    <button
                        type="submit"
                        :disabled="busy"
                        :aria-busy="busy.toString()"
                        class="btn wz-btn disabled:cursor-wait"
                    >
                        <span x-show="!busy">Confirm & Submit</span>
                        <span x-show="busy" x-cloak>Submitting&hellip;</span>
                        <i x-show="!busy" class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <i x-show="busy" x-cloak class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-dashboard-layout>
