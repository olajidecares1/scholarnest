<x-dashboard-layout page-title="Review & Confirm" page-subtitle="Review your subscription details before submitting.">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-subscription-steps step="review" />

        <x-auth-card class="!max-w-none">
            <h2 class="text-lg font-bold text-gray-900">Subscription Summary</h2>
            <p class="mt-1 text-sm text-gray-600">Please review the details below before confirming.</p>

            <dl class="mt-6 divide-y divide-gray-100 text-sm">
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Plan</dt>
                    <dd class="font-semibold text-gray-900">{{ $plan->name }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Billing Cycle</dt>
                    <dd class="font-semibold text-gray-900">{{ $billingCycle->label() }}</dd>
                </div>
                @if ($studentsCount)
                    {{-- The unit price is shown as well as the total so the
                         school can check the arithmetic itself rather than
                         being asked to trust a single figure. It comes from
                         the plan record the Super Admin configures, never a
                         number written into this page. --}}
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Price per Student</dt>
                        <dd class="font-semibold text-gray-900">&#8358;{{ number_format($plan->price_per_student_per_term, 2) }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-gray-500">Students Requested</dt>
                        <dd class="font-semibold text-gray-900">{{ number_format($studentsCount) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Billing Contact</dt>
                    <dd class="font-semibold text-gray-900">{{ $billingContactName }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Billing Email</dt>
                    <dd class="font-semibold text-gray-900">{{ $billingEmail }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Phone</dt>
                    <dd class="font-semibold text-gray-900">{{ $billingPhone }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Address</dt>
                    <dd class="font-semibold text-gray-900">{{ $billingAddress }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Payment Method</dt>
                    <dd class="font-semibold text-gray-900">{{ $paymentMethod->label() }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Receipt</dt>
                    <dd class="font-semibold text-gray-900">{{ $receiptOriginalName }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Reference</dt>
                    <dd class="font-bold text-primary-600">{{ $reference }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex items-center justify-between rounded-[5px] bg-primary-50 p-4 lg:rounded-[10px]">
                <span class="text-sm font-semibold text-gray-700">Total Amount</span>
                <span class="text-xl font-extrabold text-primary-700">&#8358;{{ number_format($amount, 2) }}</span>
            </div>

            {{-- Said before the school pays, not after. Submitting a receipt is
                 a request, not an activation: nothing opens until a Super Admin
                 has reviewed the payment. A school that learns this only from
                 the confirmation screen has already been surprised. --}}
            <div class="mt-3 flex items-start gap-2.5 rounded-[5px] bg-amber-50 p-4 lg:rounded-[10px]">
                <i class="fa-solid fa-clock mt-0.5 text-amber-600"></i>
                <div class="text-sm">
                    <p class="font-semibold text-amber-900">Activation Status: Awaiting AkademicNest Team Approval</p>
                    <p class="mt-0.5 leading-relaxed text-amber-800">
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

            <form method="POST" action="{{ route('subscriptions.review.store') }}" class="mt-6">
                @csrf

                <div class="flex items-center justify-between">
                    <a href="{{ route('subscriptions.payment-method') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back</a>

                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                    >
                        Confirm & Submit
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </form>
        </x-auth-card>
    </div>
</x-dashboard-layout>
