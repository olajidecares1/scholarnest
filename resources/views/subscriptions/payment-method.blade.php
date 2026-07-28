<x-dashboard-layout page-title="Payment Method" page-subtitle="Choose how you'd like to pay for your subscription.">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-subscription-steps :current="3" />

        <x-auth-card class="!max-w-none">
            <h2 class="text-lg font-bold text-gray-900">Select Payment Method</h2>
            <p class="mt-1 text-sm text-gray-600">Choose your preferred payment method to complete the subscription.</p>

            <form method="POST" action="{{ route('subscriptions.payment-method.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6" x-data="{ method: 'bank_transfer' }">
                @csrf

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-[8px] border-2 border-primary-500 bg-primary-50 p-4">
                        <input type="radio" name="payment_method" value="bank_transfer" x-model="method" class="mt-1">
                        <span>
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <svg class="h-5 w-5 text-primary-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 10l9-6 9 6M4 10v9h16v-9M9 19v-6h6v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                </svg>
                                Bank Transfer
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Pay via bank transfer</span>
                        </span>
                    </label>

                    <label class="flex cursor-not-allowed items-start gap-3 rounded-[8px] border border-gray-200 bg-gray-50 p-4 opacity-60">
                        <input type="radio" disabled class="mt-1">
                        <span>
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-500">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M3 10h18" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                                Paystack (Card, USSD, Transfer)
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-500">Soon</span>
                            </span>
                            <span class="mt-1 block text-xs text-gray-500">Pay instantly online</span>
                        </span>
                    </label>
                </div>

                <div class="flex items-start gap-2 rounded-[5px] bg-primary-50 p-3 text-xs text-primary-700 lg:rounded-[10px]">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                        <path d="M12 8v.01M12 11v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    After making payment via bank transfer, please upload your payment receipt below for verification.
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-900">Bank Transfer Details</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Make payment to the account below. Your subscription will be activated after verification.</p>

                    <dl class="mt-3 space-y-2 rounded-[5px] bg-gray-50 p-4 text-sm lg:rounded-[10px]">
                        <div class="flex justify-between"><dt class="text-gray-500">Bank Name</dt><dd class="font-semibold text-gray-900">GTBank</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Account Name</dt><dd class="font-semibold text-gray-900">EduNest Technologies Ltd</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Account Number</dt><dd class="font-semibold text-gray-900">0123456789</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Sort Code</dt><dd class="font-semibold text-gray-900">058152052</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2"><dt class="text-gray-500">Reference</dt><dd class="font-bold text-primary-600">{{ $reference }}</dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-gray-500">Use the reference code above when making payment.</p>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-900">Upload Payment Receipt</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Upload your bank transfer receipt or screenshot as proof of payment.</p>

                    <label
                        for="receipt"
                        class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-[8px] border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center hover:border-primary-400"
                        x-data="{ fileName: null }"
                    >
                        <svg class="h-8 w-8 text-primary-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 16V4m0 0L7 9m5-5l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-700">
                            <span x-show="!fileName">Drag &amp; drop your file here, or <span class="text-primary-500">click to browse</span></span>
                            <span x-show="fileName" x-text="fileName" x-cloak></span>
                        </p>
                        <p class="mt-1 text-xs text-gray-400">PNG, JPG, PDF up to 5MB</p>
                        <input
                            id="receipt"
                            name="receipt"
                            type="file"
                            required
                            accept=".png,.jpg,.jpeg,.pdf"
                            class="sr-only"
                            @change="fileName = $event.target.files[0]?.name"
                        >
                    </label>
                    <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('subscriptions.billing-details') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back</a>

                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                    >
                        Submit Receipt
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3M12 4v12m0-12l-4 4m4-4l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </form>
        </x-auth-card>
    </div>
</x-dashboard-layout>
