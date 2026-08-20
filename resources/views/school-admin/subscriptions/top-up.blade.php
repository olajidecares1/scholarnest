<x-dashboard-layout page-title="Add Student Slots" page-subtitle="Purchase additional student slots for this term.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Current Student Slots</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Your Basic plan is billed per student, per term — {{ $activeStudentsCount }} of {{ $subscription->students_count }} slots are currently in use.</p>

            <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-full rounded-full bg-blue-600" style="width: {{ $subscription->students_count > 0 ? min(100, round($activeStudentsCount / $subscription->students_count * 100)) : 0 }}%"></div>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Request Additional Slots</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Your request is reviewed by a Super Administrator before your student limit increases — this is not automatic.</p>

            <form
                method="POST"
                action="{{ route('subscription-top-up.store') }}"
                enctype="multipart/form-data"
                class="mt-6 space-y-6"
                x-data="{ additionalStudentsCount: 1 }"
            >
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Additional Students Needed</label>
                    <input
                        type="number"
                        name="additional_students_count"
                        x-model.number="additionalStudentsCount"
                        min="1"
                        class="mt-1 w-full rounded-[8px] border-gray-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Amount due:
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="'₦' + (additionalStudentsCount * {{ (float) $plan->price_per_student_per_term }}).toLocaleString()"></span>
                        (₦{{ number_format($plan->price_per_student_per_term, 0) }} per student, per term)
                    </p>
                    <x-input-error :messages="$errors->get('additional_students_count')" class="mt-2" />
                </div>

                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Bank Transfer Details</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Make payment to the account below, then upload your receipt. Your limit increases once verified.</p>

                    <dl class="mt-3 space-y-2 rounded-[5px] bg-gray-50 p-4 text-sm dark:bg-gray-900/40 lg:rounded-[10px]">
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Bank Name</dt><dd class="font-semibold text-gray-900 dark:text-white">GTBank</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Account Name</dt><dd class="font-semibold text-gray-900 dark:text-white">EduNest Technologies Ltd</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Account Number</dt><dd class="font-semibold text-gray-900 dark:text-white">0123456789</dd></div>
                    </dl>
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
    </div>
</x-dashboard-layout>
