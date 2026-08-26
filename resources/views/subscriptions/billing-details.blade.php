<x-dashboard-layout page-title="Billing Details" page-subtitle="Confirm the billing information for your school.">
    <div class="mx-auto max-w-2xl space-y-6">
        <x-subscription-steps step="billing-details" />

        <x-auth-card class="!max-w-none">
            <h2 class="text-lg font-bold text-gray-900">Billing Contact Information</h2>
            <p class="mt-1 text-sm text-gray-600">This information will appear on your subscription records and receipts.</p>

            <form method="POST" action="{{ route('subscriptions.billing-details.store') }}" class="mt-6 space-y-2">
                @csrf

                <x-text-field
                    id="billing_contact_name"
                    name="billing_contact_name"
                    label="Contact Name"
                    icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                    helper="Who should we contact about billing and receipts?"
                    :value="old('billing_contact_name', $billingContactName)"
                    required
                    placeholder="Full name of the billing contact"
                />

                <x-auth-email-input
                    id="billing_email"
                    name="billing_email"
                    label="Billing Email"
                    placeholder="Enter your billing email address"
                    helper="Receipts and payment confirmations will be sent here."
                    :value="old('billing_email', $billingEmail)"
                />

                <x-text-field
                    id="billing_phone"
                    name="billing_phone"
                    label="Phone Number"
                    type="tel"
                    icon="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1.5 1.5 0 01-1.6 1.5A16.5 16.5 0 015 5.1a1.5 1.5 0 011.5-1.6z"
                    helper="A number we can reach you on for billing questions."
                    :value="old('billing_phone', $billingPhone)"
                    required
                    placeholder="Enter your phone number"
                />

                <x-text-field
                    id="billing_address"
                    name="billing_address"
                    label="Address"
                    icon="M12 21s7-6.5 7-11.5A7 7 0 105 9.5C5 14.5 12 21 12 21z M12 11.5a2 2 0 100-4 2 2 0 000 4z"
                    helper="School or office address to appear on your invoices."
                    :value="old('billing_address', $billingAddress)"
                    required
                    placeholder="School or office address"
                />

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ $backRoute }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back</a>

                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                    >
                        Continue
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </form>
        </x-auth-card>
    </div>
</x-dashboard-layout>
