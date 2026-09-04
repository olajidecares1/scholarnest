{{-- How many students the school is onboarding, and what that costs.

     The unit price is read from the plan record the Super Admin configures and
     handed to Alpine as a number; nothing here writes an amount of its own, so
     a price change takes effect on this page without it being touched. The
     total shown is a quote - the charge is recalculated server-side from the
     same plan record when the form is submitted. --}}
<x-dashboard-layout page-title="Students & Amount" page-subtitle="Tell us how many students you are onboarding this term.">
    <div class="mx-auto max-w-2xl space-y-6">
        <x-subscription-steps step="students" />

        <x-auth-card class="!max-w-none">
            <h2 class="text-lg font-bold text-gray-900">Student Capacity</h2>
            <p class="mt-1 text-sm text-gray-600">
                Your subscription is billed per student, per term. The number you enter here also becomes
                the number of student spaces your school is given once your payment is approved.
            </p>

            <form
                method="POST"
                action="{{ route('subscriptions.students.store') }}"
                class="mt-6"
                x-data="{
                    studentsCount: {{ (int) old('students_count', $studentsCount ?: 1) }},
                    pricePerStudent: {{ (float) $plan->price_per_student_per_term }},
                    get total() {
                        const count = Number(this.studentsCount)

                        return count > 0 ? count * this.pricePerStudent : 0
                    },
                    get formattedTotal() {
                        return '\u20A6' + this.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    },
                }"
            >
                @csrf

                <label for="students_count" class="field-label">
                    Number of Students / Pupils
                </label>

                <div class="mt-2 flex items-stretch gap-2">
                    <button
                        type="button"
                        @click="studentsCount = Math.max(1, Number(studentsCount) - 1)"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] border border-gray-300 text-gray-600 transition hover:bg-gray-50"
                        aria-label="Decrease number of students"
                    >
                        <i class="fa-solid fa-minus text-xs"></i>
                    </button>

                    <input
                        type="number"
                        id="students_count"
                        name="students_count"
                        x-model.number="studentsCount"
                        min="1"
                        max="100000"
                        step="1"
                        required
                        inputmode="numeric"
                        class="w-full text-center"
                    >

                    <button
                        type="button"
                        @click="studentsCount = Math.min(100000, Number(studentsCount) + 1)"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] border border-gray-300 text-gray-600 transition hover:bg-gray-50"
                        aria-label="Increase number of students"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                    </button>
                </div>

                <x-input-error :messages="$errors->get('students_count')" class="mt-2" />

                <p class="mt-2 text-xs text-gray-500">
                    You can request additional student spaces later without starting a new subscription.
                </p>

                <div class="mt-6 rounded-[5px] border border-gray-200 bg-gray-50 p-4 lg:rounded-[10px]">
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Plan</dt>
                            <dd class="font-semibold text-gray-900">{{ $plan->name }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Price per Student / Term</dt>
                            <dd class="font-semibold text-gray-900">&#8358;{{ number_format($plan->price_per_student_per_term, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">Students</dt>
                            <dd class="font-semibold text-gray-900" x-text="Number(studentsCount) > 0 ? Number(studentsCount).toLocaleString() : '—'"></dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
                        <span class="text-sm font-semibold text-gray-700">Total Amount</span>
                        <span class="text-xl font-extrabold text-primary-700" x-text="formattedTotal"></span>
                    </div>
                </div>

                <div class="mt-6 flex items-start gap-2.5 rounded-[5px] bg-amber-50 p-4 lg:rounded-[10px]">
                    <i class="fa-solid fa-circle-info mt-0.5 text-amber-600"></i>
                    <p class="text-sm leading-relaxed text-amber-800">
                        This is your school's total student capacity for the term. Your school is activated with these
                        spaces only after a ScholarNest Team has verified your payment.
                    </p>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('subscriptions.choose-plan') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back</a>

                    <button
                        type="submit"
                        :disabled="!(Number(studentsCount) >= 1)"
                        :class="Number(studentsCount) >= 1 ? 'bg-primary-500 hover:bg-primary-600' : 'cursor-not-allowed bg-gray-300'"
                        class="flex items-center gap-2 rounded-[8px] px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition"
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
