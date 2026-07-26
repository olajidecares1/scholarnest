<x-dashboard-layout page-title="Billing Details" page-subtitle="Confirm the billing information for your school.">
    <div class="mx-auto max-w-2xl space-y-6">
        <x-subscription-steps :current="2" />

        <x-auth-card class="!max-w-none">
            <h2 class="text-lg font-bold text-gray-900">Billing Contact Information</h2>
            <p class="mt-1 text-sm text-gray-600">This information will appear on your subscription records and receipts.</p>

            <form method="POST" action="{{ route('subscriptions.billing-details.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <x-input-label for="billing_contact_name" value="Contact Name" />
                    <x-text-input
                        id="billing_contact_name"
                        name="billing_contact_name"
                        type="text"
                        class="mt-1"
                        :value="old('billing_contact_name', $billingContactName)"
                        required
                        placeholder="Full name of the billing contact"
                    />
                    <x-input-error :messages="$errors->get('billing_contact_name')" class="mt-2" />
                </div>

                <x-auth-email-input
                    id="billing_email"
                    name="billing_email"
                    label="Billing Email"
                    placeholder="Enter your billing email address"
                    :value="old('billing_email', $billingEmail)"
                />

                <div>
                    <x-input-label for="billing_phone" value="Phone Number" />
                    <x-text-input
                        id="billing_phone"
                        name="billing_phone"
                        type="tel"
                        class="mt-1"
                        :value="old('billing_phone', $billingPhone)"
                        required
                        placeholder="Enter your phone number"
                    />
                    <x-input-error :messages="$errors->get('billing_phone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="billing_address" value="Address" />
                    <x-text-input
                        id="billing_address"
                        name="billing_address"
                        type="text"
                        class="mt-1"
                        :value="old('billing_address', $billingAddress)"
                        required
                        placeholder="School or office address"
                    />
                    <x-input-error :messages="$errors->get('billing_address')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('subscriptions.choose-plan') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back</a>

                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-[5px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 lg:rounded-[10px]"
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
