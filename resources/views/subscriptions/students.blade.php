{{-- How many students the school is onboarding, and what that costs.

     The unit price is read from the plan record the Super Admin configures and
     handed to Alpine as a number; nothing here writes an amount of its own, so
     a price change takes effect on this page without it being touched. The
     total shown is a quote, the charge is recalculated server-side from the
     same plan record when the form is submitted. --}}
<x-dashboard-layout page-title="Students & Amount" page-subtitle="Tell us how many students you are onboarding this term.">
    <div class="wz-shell">
        <x-subscription-steps step="students" />

        <div class="wz-card">
            <h2 class="wz-title">Student Capacity</h2>
            <small class="wz-lead">
                Your subscription is billed per student, per term. The number you enter here also becomes
                the number of student spaces your school is given once your payment is approved.
            </small>

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

                <div class="mt-1.5 flex items-stretch gap-2">
                    <button
                        type="button"
                        @click="studentsCount = Math.max(1, Number(studentsCount) - 1)"
                        class="flex h-9 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        aria-label="Decrease number of students"
                    >
                        <i class="fa-solid fa-minus text-[11px]" aria-hidden="true"></i>
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
                        class="w-full text-center font-semibold tabular-nums"
                    >

                    <button
                        type="button"
                        @click="studentsCount = Math.min(100000, Number(studentsCount) + 1)"
                        class="flex h-9 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        aria-label="Increase number of students"
                    >
                        <i class="fa-solid fa-plus text-[11px]" aria-hidden="true"></i>
                    </button>
                </div>

                <x-input-error :messages="$errors->get('students_count')" class="mt-2" />

                <small class="field-hint mt-1.5">
                    You can request additional student spaces later without starting a new subscription.
                </small>

                <div class="wz-panel mt-5 overflow-hidden">
                    <dl class="wz-rows">
                        <div class="wz-row">
                            <dt>Plan</dt>
                            <dd>{{ $plan->name }}</dd>
                        </div>
                        <div class="wz-row">
                            <dt>Price per Student / Term</dt>
                            <dd>&#8358;{{ number_format($plan->price_per_student_per_term, 2) }}</dd>
                        </div>
                        <div class="wz-row">
                            <dt>Students</dt>
                            <dd class="tabular-nums" x-text="Number(studentsCount) > 0 ? Number(studentsCount).toLocaleString() : 'N/A'"></dd>
                        </div>
                    </dl>

                    <div class="wz-total rounded-t-none border-t border-slate-200/70 dark:border-gray-700">
                        <span class="wz-total-label">Total Amount</span>
                        <span class="wz-total-value tabular-nums" x-text="formattedTotal"></span>
                    </div>
                </div>

                <div class="wz-note wz-note-warn mt-4">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>
                        This is your school's total student capacity for the term. Your school is activated with these
                        spaces only after a AkademicNest Team has verified your payment.
                    </p>
                </div>

                <div class="wz-actions">
                    <a href="{{ route('subscriptions.choose-plan') }}" class="wz-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>

                    <button
                        type="submit"
                        :disabled="!(Number(studentsCount) >= 1)"
                        class="btn wz-btn"
                    >
                        Continue
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-dashboard-layout>
