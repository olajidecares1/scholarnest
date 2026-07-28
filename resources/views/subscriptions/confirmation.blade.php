<x-dashboard-layout page-title="Payment Confirmed & Sent" page-subtitle="Your payment has been received and is under review.">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-subscription-steps :current="5" />

        <x-auth-card class="!max-w-none text-center">
            <div class="flex justify-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
            </div>

            <p class="mt-4 text-sm font-bold uppercase tracking-wide text-primary-600">Thank You!</p>
            <h2 class="mt-1 text-2xl font-extrabold text-gray-900">Your Payment Has Been Received</h2>
            <p class="mt-2 text-sm text-gray-600">
                We have successfully received your payment receipt.
                Our team will verify your payment and activate your subscription shortly.
            </p>

            <div class="mt-6 flex items-start gap-3 rounded-[5px] bg-green-50 p-4 text-left lg:rounded-[10px]">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5" />
                    <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                <div>
                    <p class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        Payment Status
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase text-green-700">Received</span>
                    </p>
                    <p class="mt-0.5 text-xs text-gray-600">Your payment is under review and will be confirmed within 24 hours.</p>
                </div>
            </div>

            <div class="mt-8 text-left">
                <h3 class="text-center text-sm font-bold text-gray-900">Payment & Subscription Summary</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                        <p class="text-xs text-gray-500">Plan Selected</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $subscription->plan->name }}</p>
                    </div>
                    <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                        <p class="text-xs text-gray-500">Billing Cycle</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $subscription->billing_cycle->label() }}</p>
                    </div>
                    <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                        <p class="text-xs text-gray-500">Amount Paid</p>
                        <p class="mt-1 font-semibold text-gray-900">&#8358;{{ number_format($subscription->amount, 2) }}</p>
                    </div>
                    <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                        <p class="text-xs text-gray-500">Reference</p>
                        <p class="mt-1 font-semibold text-gray-900">{{ $subscription->reference }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-4 text-left sm:grid-cols-2">
                <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                    <p class="text-sm font-bold text-gray-900">What Happens Next?</p>
                    <ul class="mt-2 space-y-1.5 text-xs text-gray-600">
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> We will verify your payment receipt</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> You will receive an email confirmation once verified</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> Your subscription will be activated</li>
                        <li class="flex items-start gap-1.5"><span class="text-primary-500">&check;</span> You can then set up your school website and manage your platform</li>
                    </ul>
                </div>
                <div class="rounded-[5px] border border-gray-200 p-4 lg:rounded-[10px]">
                    <p class="text-sm font-bold text-gray-900">Need Help?</p>
                    <p class="mt-2 text-xs text-gray-600">If you have any questions, our support team is here to help you.</p>
                    <a href="#" class="mt-3 inline-block rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700">Contact Support</a>
                </div>
            </div>

            <a
                href="{{ route('dashboard') }}"
                class="mt-8 inline-flex items-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
            >
                Back to Dashboard
            </a>
        </x-auth-card>
    </div>
</x-dashboard-layout>
