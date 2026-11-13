<x-dashboard-layout page-title="Payment Method" page-subtitle="Choose how you'd like to pay for your subscription.">
    <div class="wz-shell">
        <x-subscription-steps step="payment-method" />

        <div class="wz-card">
            <h2 class="wz-title">Select Payment Method</h2>
            <small class="wz-lead">Choose your preferred payment method to complete the subscription.</small>

            @if ($paymentMethods->isEmpty())
                {{-- Not an empty list of radios above a button that cannot
                     succeed. The AkademicNest Team has turned everything off, and
                     the honest thing is to say so and offer the way back. --}}
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-center dark:border-amber-900/50 dark:bg-amber-900/20">
                    <p class="text-[13px] font-semibold text-amber-900">No payment methods are available right now</p>
                    <p class="mx-auto mt-1.5 max-w-md text-[12.5px] leading-[1.7] text-amber-800">
                        The AkademicNest Team has not enabled any way to pay at the moment. Your plan choice has been
                        saved &mdash; please try again shortly, or contact the AkademicNest Team.
                    </p>

                    <a
                        href="{{ route('subscriptions.billing-details') }}"
                        class="btn mt-4 inline-flex items-center justify-center gap-2 border border-amber-300 bg-white font-semibold text-amber-800 transition hover:bg-amber-100"
                    >
                        <i class="fa-solid fa-arrow-left btn-icon" aria-hidden="true"></i> Back
                    </a>
                </div>
            @else
            <form method="POST" action="{{ route('subscriptions.payment-method.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5"
                  x-data="{ method: '{{ $paymentMethods->first()->key }}', busy: false, fileProblem: null }"
                  @pageshow.window="busy = false" @submit="if (busy || fileProblem) { $event.preventDefault(); return; } busy = true">
                @csrf

                {{-- Every option comes from the payment_methods table. A method
                     the AkademicNest Team disables is not here AND is refused by
                     PaymentMethodRequest, which is the half that matters. --}}
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    @foreach ($paymentMethods as $paymentMethod)
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                            :class="method === '{{ $paymentMethod->key }}' ? 'border-primary-500 bg-primary-50/60 ring-1 ring-primary-500 dark:bg-primary-900/20' : 'border-slate-200 bg-white hover:border-slate-300 dark:border-gray-600 dark:bg-gray-800'"
                        >
                            <input type="radio" name="payment_method" value="{{ $paymentMethod->key }}" x-model="method" class="mt-0.5">
                            <span>
                                <span class="flex items-center gap-2 text-[13px] font-semibold text-slate-900 dark:text-white">
                                    <svg class="h-4 w-4 text-primary-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 10l9-6 9 6M4 10v9h16v-9M9 19v-6h6v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                    </svg>
                                    {{ $paymentMethod->label }}
                                </span>
                                @if ($paymentMethod->description)
                                    <small class="mt-0.5 block text-[11.5px] leading-[1.5] text-slate-500 dark:text-gray-400">{{ $paymentMethod->description }}</small>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />

                @foreach ($paymentMethods as $paymentMethod)
                    <div x-show="method === '{{ $paymentMethod->key }}'" x-cloak class="space-y-4">
                        @if ($paymentMethod->instructions)
                            <div class="wz-note wz-note-info">
                                <svg class="mt-[2px] h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M12 8v.01M12 11v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                {{ $paymentMethod->instructions }}
                            </div>
                        @endif

                        @if ($paymentMethod->bankFields())
                            <div>
                                <h3 class="wz-section-title">{{ $paymentMethod->label }} Details</h3>

                                <dl class="wz-panel wz-rows mt-2.5">
                                    @foreach ($paymentMethod->bankFields() as $fieldLabel => $fieldValue)
                                        <div class="wz-row">
                                            <dt>{{ $fieldLabel }}</dt>
                                            <dd class="tabular-nums">{{ $fieldValue }}</dd>
                                        </div>
                                    @endforeach

                                    <div class="wz-row">
                                        <dt>Reference</dt>
                                        <dd class="font-semibold !text-primary-600 select-all">{{ $reference }}</dd>
                                    </div>
                                </dl>

                                <small class="field-hint mt-1.5">Use the reference code above when making payment.</small>
                            </div>
                        @endif
                    </div>
                @endforeach
                <div>
                    <h3 class="wz-section-title">Upload Payment Receipt</h3>
                    <small class="field-hint mt-0.5">Upload your bank transfer receipt or screenshot as proof of payment.</small>

                    <label
                        for="receipt"
                        class="mt-2.5 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-6 text-center transition hover:border-primary-400 hover:bg-primary-50/40 dark:border-gray-600 dark:bg-gray-900/30"
                        x-data="{ fileName: null }"
                    >
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-primary-500 shadow-sm ring-1 ring-slate-200 dark:bg-gray-800 dark:ring-gray-700"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 16V4m0 0L7 9m5-5l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg></span>
                        <p class="mt-2.5 max-w-full truncate text-[12.5px] font-medium text-slate-700 dark:text-gray-200">
                            <span x-show="!fileName">Drag &amp; drop your file here, or <span class="text-primary-500">click to browse</span></span>
                            <span x-show="fileName" x-cloak class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300"><i class="fa-solid fa-circle-check text-[11px]" aria-hidden="true"></i><span x-text="fileName"></span></span>
                        </p>
                        <small class="mt-1 block text-[11px] text-slate-400">PNG, JPG, PDF up to 5MB</small>
                        {{-- Checked here first so a school learns at once that a file
                             is too large or the wrong kind, rather than after waiting
                             for it to upload. The server checks again regardless. --}}
                        <input
                            id="receipt"
                            name="receipt"
                            type="file"
                            required
                            accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf"
                            class="sr-only"
                            @change="
                                const file = $event.target.files[0];
                                fileName = file?.name;
                                fileProblem = null;
                                if (file && ! /\.(png|jpe?g|pdf)$/i.test(file.name)) {
                                    fileProblem = 'Please choose a PNG, JPG or PDF file.';
                                } else if (file && /\.pdf$/i.test(file.name) && file.size > {{ \App\Http\Requests\Subscriptions\PaymentMethodRequest::MAX_KILOBYTES }} * 1024) {
                                    {{-- PDFs only: a large photo is made smaller by the
                                         browser before it is sent (image-upload-prep.js). --}}
                                    fileProblem = 'This PDF is ' + (file.size / 1048576).toFixed(1) + 'MB. The receipt must be 5MB or smaller: a screenshot of the receipt is usually much smaller.';
                                }
                            "
                        >
                    </label>
                    <p x-show="fileProblem" x-text="fileProblem" x-cloak class="mt-2 text-[12px] text-red-600" role="alert"></p>
                    <x-input-error :messages="$errors->get('receipt')" class="mt-2" />
                </div>

                <div class="wz-actions !mt-1">
                    <a href="{{ route('subscriptions.billing-details') }}" class="wz-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>

                    <button
                        type="submit"
                        :disabled="busy"
                        :aria-busy="busy.toString()"
                        class="btn wz-btn disabled:cursor-wait"
                    >
                        <span x-show="!busy">Submit Receipt</span>
                        <span x-show="busy" x-cloak>Uploading&hellip;</span>
                        <i x-show="!busy" class="fa-solid fa-upload" aria-hidden="true"></i>
                        <i x-show="busy" x-cloak class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-dashboard-layout>
