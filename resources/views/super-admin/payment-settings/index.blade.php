{{-- Payment settings.

     What a school is told to pay into, and which ways of paying are open.
     These were typed into the subscription template until now, which meant
     changing a bank account required a deploy. --}}
<x-super-admin-layout
    page-title="Payment Settings"
    page-subtitle="The payment methods and account details schools see when they subscribe or top up."
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($methods->where('is_enabled', true)->isEmpty())
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-900/20">
                <p class="text-sm font-bold text-amber-900 dark:text-amber-300">No payment method is enabled</p>
                <p class="mt-1 text-[12.5px] leading-[1.7] text-amber-800 dark:text-amber-400">
                    No school can subscribe or top up while every method is off. The payment step tells them so
                    rather than showing an empty form, but nobody can pay until one of these is enabled.
                </p>
            </div>
        @endif

        @foreach ($methods as $method)
            <div
                class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                x-data="{ editing: false }"
            >
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-6 dark:border-gray-700">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $method->label }}</h2>

                            @if ($method->is_enabled)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    Enabled
                                </span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    Disabled
                                </span>
                            @endif

                            @if ($method->requires_receipt)
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    Proof of payment required
                                </span>
                            @endif
                        </div>

                        @if ($method->description)
                            <small class="block mt-1 text-[12.5px] text-gray-500 dark:text-gray-400">{{ $method->description }}</small>
                        @endif

                        {{-- Said loudly, because money sent to a placeholder
                             does not come back. --}}
                        @if ($method->usesShippedPlaceholder())
                            <p class="mt-3 flex items-start gap-2 rounded-[8px] border border-red-200 bg-red-50 p-3 text-[12px] leading-[1.6] text-red-800 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
                                <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
                                <span>
                                    These are the placeholder details that shipped with AkademicNest, not a real account.
                                    Replace them before any school pays.
                                </span>
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            @click="editing = ! editing"
                            class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <span x-show="!editing">Edit details</span>
                            <span x-show="editing" x-cloak>Cancel</span>
                        </button>

                        <form method="POST" action="{{ route('super-admin.payment-settings.toggle', $method) }}">
                            @csrf
                            <button
                                type="submit"
                                class="btn rounded-[8px] px-3 py-1.5 text-xs font-bold text-white transition {{ $method->is_enabled ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-600 hover:bg-green-700' }}"
                            >
                                {{ $method->is_enabled ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </div>
                </div>

                {{-- What a school currently sees. --}}
                <div class="p-6" x-show="!editing">
                    @if ($method->bankFields())
                        <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            @foreach ($method->bankFields() as $label => $value)
                                <div class="flex justify-between gap-4 rounded-[8px] bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                    <dd class="text-right font-semibold text-gray-900 dark:text-white">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <small class="block text-sm text-gray-500 dark:text-gray-400">
                            No account details entered. Schools choosing this method will see the instructions only.
                        </small>
                    @endif

                    @if ($method->instructions)
                        <small class="block mt-4 rounded-[8px] bg-gray-50 p-3 text-[12.5px] leading-[1.7] text-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
                            {{ $method->instructions }}
                        </small>
                    @endif
                </div>

                <form method="POST" action="{{ route('super-admin.payment-settings.update', $method) }}" class="space-y-4 p-6" x-show="editing" x-cloak>
                    @csrf
                    @method('PUT')

                    {{-- EVERY FIELD ID IS SCOPED TO ITS METHOD.

                         This page renders one form per payment method, and the
                         fields used to carry bare ids, so two inputs on the
                         page were both id="account_number", and both
                         <label for="account_number"> pointed at whichever came
                         first. Clicking "Account Number" on the second card
                         put the cursor in the FIRST card's field, and the
                         Super Admin typed their new account number into the
                         wrong form. It saved perfectly, to the wrong row, and
                         every school carried on seeing the old details. --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field :id="$method->key.'-label'" name="label" label="Name shown to schools" :value="$method->label" :error-bag="$method->key" required />
                        <x-text-field :id="$method->key.'-description'" name="description" label="Short description" :value="$method->description" :error-bag="$method->key" helper="Optional." />

                        {{-- Bank fields only where a school actually transfers
                             money. Paystack collects it itself, so offering
                             them there invites somebody to fill in a row
                             nothing reads. --}}
                        @if ($method->usesBankAccount())
                            <x-text-field :id="$method->key.'-bank_name'" name="bank_name" label="Bank Name" :value="$method->detail('bank_name')" :error-bag="$method->key" />
                            <x-text-field :id="$method->key.'-account_name'" name="account_name" label="Account Name" :value="$method->detail('account_name')" :error-bag="$method->key" />
                            <x-text-field :id="$method->key.'-account_number'" name="account_number" label="Account Number" :value="$method->detail('account_number')" :error-bag="$method->key" helper="Digits only." />
                            <x-text-field :id="$method->key.'-sort_code'" name="sort_code" label="Sort Code" :value="$method->detail('sort_code')" :error-bag="$method->key" helper="Optional." />
                        @endif
                    </div>

                    <x-textarea-field
                        :id="$method->key.'-instructions'"
                        name="instructions"
                        label="Instructions shown to schools"
                        :value="$method->instructions"
                        rows="3"
                        :error-bag="$method->key"
                        helper="What the school should do to pay. Shown above the account details."
                    />

                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn rounded-[8px] bg-primary-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-primary-700">
                            Save
                        </button>
                        <small class="block text-xs text-gray-500 dark:text-gray-400">Schools see the change immediately.</small>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</x-super-admin-layout>
