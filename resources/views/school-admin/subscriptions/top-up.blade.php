<x-dashboard-layout page-title="Add More Students/Pupils" page-subtitle="Request additional student/pupil spaces for this term.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($capacity === null)
            {{-- A per-student plan whose student count was never recorded. The
                 request below adds to a capacity, so there is nothing honest
                 to add to, and inventing a starting figure would misreport
                 what the school paid for. --}}
            <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-900/20 lg:rounded-[10px]">
                <p class="flex items-center gap-2 text-sm font-bold text-amber-900 dark:text-amber-200">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Your student/pupil capacity is not recorded yet
                </p>
                <p class="mt-2 text-sm leading-relaxed text-amber-900/90 dark:text-amber-200/90">
                    This subscription does not say how many students/pupils it covers, so additional spaces cannot be
                    requested against it. Please contact the AkademicNest Team and they will set it for your school.
                    Nothing is wrong with your account, and your existing students/pupils are unaffected.
                </p>
                <a href="{{ route('dashboard') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-900 underline dark:text-amber-200">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                    Back to the dashboard
                </a>
            </div>
        @else
            {{-- The same card the dashboard shows, minus the button that leads
                 here. One component, so the figures cannot diverge between the
                 page that reports capacity and the page that asks for more. --}}
            <x-student-capacity-card :capacity="$capacity" :action="false" />

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Request Additional Student/Pupil Spaces</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Your request is reviewed by the AkademicNest Team before your capacity increases. This is not automatic,
                and the spaces cannot be used until it is approved.
            </p>

            <form
                method="POST"
                action="{{ route('subscription-top-up.store') }}"
                enctype="multipart/form-data"
                class="mt-6 space-y-2"
                x-data="{ additionalStudentsCount: 1 }"
            >
                @csrf

                <div>
                    <label for="additional_students_count" class="field-label">Additional Spaces Requested</label>
                    <input
                        id="additional_students_count"
                        type="number"
                        name="additional_students_count"
                        x-model.number="additionalStudentsCount"
                        min="1"
                        max="100000"
                        required
                        inputmode="numeric"
                        class="mt-1 w-full"
                    >
                    <x-input-error :messages="$errors->get('additional_students_count')" class="mt-2" />

                    {{-- The unit price comes from the plan record the Super
                         Admin configures. The total on screen is a quote; the
                         charge is worked out again on the server from the same
                         record when this form is submitted. --}}
                    <dl class="mt-3 space-y-1.5 rounded-[5px] bg-gray-50 p-4 text-sm dark:bg-gray-900/40 lg:rounded-[10px]">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Current Capacity</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($capacity['allocated']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Additional Spaces Requested</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white" x-text="Number(additionalStudentsCount) > 0 ? Number(additionalStudentsCount).toLocaleString() : 'N/A'"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Price per Student/Pupil</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">&#8358;{{ number_format($plan->price_per_student_per_term, 2) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-1.5 dark:border-gray-700">
                            <dt class="font-semibold text-gray-700 dark:text-gray-200">Amount Due</dt>
                            <dd
                                class="text-base font-extrabold text-primary-700 dark:text-primary-400"
                                x-text="'₦' + (Math.max(0, Number(additionalStudentsCount)) * {{ (float) $plan->price_per_student_per_term }}).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"
                            ></dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-1.5 dark:border-gray-700">
                            <dt class="text-gray-500 dark:text-gray-400">Capacity if approved</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white" x-text="({{ (int) $capacity['allocated'] }} + Math.max(0, Number(additionalStudentsCount))).toLocaleString()"></dd>
                        </div>
                    </dl>
                </div>

                {{-- THE ACCOUNT COMES FROM PAYMENT SETTINGS, not from here.

                     These three lines were typed into this template, so a
                     school topping up was shown GTBank / AkademicNest
                     Technologies Ltd / 0123456789 whatever the AkademicNest
                     Team had actually saved, the placeholder that shipped
                     with the migration, on every plan, for ever. The
                     subscription wizard was moved onto the database and this
                     page was missed, which is why updating Payment Settings
                     appeared to change nothing here.

                     Money transferred to a placeholder does not come back, so
                     when no account has been entered this says so rather than
                     inventing one. --}}
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $bankTransfer?->label ?? 'Bank Transfer' }} Details</h3>
                    <p class="field-hint mt-0.5">
                        {{ $bankTransfer?->instructions ?: 'Make payment to the account below, then upload your receipt. Your limit increases once verified.' }}
                    </p>

                    @if ($bankTransfer?->bankFields())
                        <dl class="mt-3 space-y-2 rounded-[5px] bg-gray-50 p-4 text-sm dark:bg-gray-900/40 lg:rounded-[10px]">
                            @foreach ($bankTransfer->bankFields() as $fieldLabel => $fieldValue)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $fieldLabel }}</dt>
                                    <dd class="font-semibold text-gray-900 dark:text-white">{{ $fieldValue }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-3 rounded-[5px] bg-amber-50 p-4 text-sm font-semibold text-amber-900 lg:rounded-[10px]">
                            No payment account has been set up yet. Please contact AkademicNest before transferring anything.
                        </p>
                    @endif

                    <input type="hidden" name="payment_method" value="bank_transfer">
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Upload Payment Receipt</h3>

                    <label
                        for="receipt"
                        class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-[8px] border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center hover:border-blue-400 dark:border-gray-600 dark:bg-gray-900/40"
                        x-data="{ fileName: null }"
                    >
                        <svg class="h-8 w-8 text-blue-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 16V4m0 0L7 9m5-5l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                            <span x-show="!fileName">Drag &amp; drop your file here, or <span class="text-blue-500">click to browse</span></span>
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

                <div class="flex items-center justify-end pt-2">
                    <button
                        type="submit"
                        class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                    >
                        Submit Request
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3M12 4v12m0-12l-4 4m4-4l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        {{-- Payment, approval and capacity history. These rows are a record of
             what was asked for and what was decided, they are NOT separate
             usable allowances. The school's usable capacity is the single
             cumulative figure in the card above. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Capacity Request History</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Every request you have made, and what each one was decided. Only approved requests add to your capacity.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Request</th>
                            <th class="py-2 pr-4 font-semibold">Additional Students/Pupils</th>
                            <th class="py-2 pr-4 font-semibold">Amount</th>
                            <th class="py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <tr>
                            <td class="py-3 pr-4 font-semibold text-gray-900 dark:text-white">Initial</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ number_format($capacity['initial']) }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">&#8358;{{ number_format($initialAmount, 2) }}</td>
                            <td class="py-3">
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">Approved</span>
                            </td>
                        </tr>

                        @foreach ($history as $index => $request)
                            <tr>
                                <td class="py-3 pr-4 font-semibold text-gray-900 dark:text-white">
                                    Request #{{ $index + 2 }}
                                    <span class="block text-xs font-normal text-gray-400">{{ $request->created_at->format('d M Y') }}</span>
                                </td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">
                                    {{ number_format($request->additional_students_count) }}
                                    @if ($request->status === \App\Enums\SubscriptionTopUpStatus::Approved && $request->approved_students_count !== $request->additional_students_count)
                                        <span class="block text-xs text-gray-400">{{ number_format($request->approved_students_count) }} approved</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">&#8358;{{ number_format($request->additional_amount, 2) }}</td>
                                <td class="py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-bold',
                                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $request->status === \App\Enums\SubscriptionTopUpStatus::Approved,
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $request->status === \App\Enums\SubscriptionTopUpStatus::PendingVerification,
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $request->status === \App\Enums\SubscriptionTopUpStatus::Rejected,
                                    ])>{{ $request->status->label() }}</span>

                                    @if ($request->status === \App\Enums\SubscriptionTopUpStatus::Approved)
                                        <span class="block text-xs text-gray-400">{{ number_format($request->previous_students_count) }} &rarr; {{ number_format($request->new_students_count) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($history->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">You have not requested any additional spaces yet.</p>
            @endif
        </div>
        @endif
    </div>
</x-dashboard-layout>
