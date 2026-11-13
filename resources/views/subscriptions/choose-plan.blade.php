<x-dashboard-layout page-title="Choose Your Plan" page-subtitle="Select a subscription plan that best fits your school's needs.">
    <div class="wz-shell-wide">
        <x-subscription-steps step="choose-plan" />

        <form
            method="POST"
            action="{{ route('subscriptions.choose-plan.store') }}"
            x-data="{
                planId: {{ $selectedPlanId ?? 'null' }},
                billingCycle: 'per_term',
                planKey(id) {
                    const plans = {{ $plans->pluck('key.value', 'id')->toJson() }};
                    return plans[id] ?? null;
                },
            }"
        >
            @csrf

            @if ($errors->has('subscription'))
                <div class="wz-note wz-note-error mb-4" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>{{ $errors->first('subscription') }}</span>
                </div>
            @endif
            <x-input-error :messages="$errors->get('plan_id')" class="mb-4" />
            <x-input-error :messages="$errors->get('billing_cycle')" class="mb-4" />

            {{-- Never a blank page. With no plans there is nothing to choose
                 and Continue can never be pressed, so say so instead of
                 leaving the school looking at an empty screen. --}}
            @if ($plans->isEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-center text-[12.5px] text-amber-900">
                    <p class="font-bold">No subscription plans are available right now.</p>
                    <p class="mt-1">
                        Please try again shortly, or contact
                        @if ($supportEmail = \App\Models\Setting::supportEmail())
                            <a href="mailto:{{ $supportEmail }}" class="font-semibold underline">{{ $supportEmail }}</a>.
                        @else
                            AkademicNest support.
                        @endif
                    </p>
                </div>
            @endif

            <div class="wz-stagger grid grid-cols-1 gap-4 pt-2 md:grid-cols-2 lg:grid-cols-3 lg:gap-5" style="--wz-base: 120ms; --wz-step: 90ms">
                @foreach ($plans as $plan)
                    @php $available = $plan->key->isAvailableToSubscribe(); @endphp

                    <label
                        @class([
                            'relative flex flex-col rounded-2xl border bg-white p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-20px_rgba(15,23,42,0.25)] transition sm:p-6 dark:bg-gray-800',
                            'cursor-pointer' => $available,
                            // Coming soon: visibly out of reach, and with no
                            // radio inside it to select. The server refuses it
                            // too, see PlanKey::isAvailableToSubscribe.
                            'cursor-not-allowed border-slate-200 opacity-60 dark:border-gray-700' => ! $available,
                        ])
                        @if ($available)
                            :class="planId === {{ $plan->id }} ? 'border-primary-500 ring-[3px] ring-primary-100 dark:ring-primary-900/60' : 'border-slate-200/80 hover:border-slate-300 hover:-translate-y-0.5 dark:border-gray-700'"
                        @endif
                    >
                        @if ($plan->is_popular && $available)
                            <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary-600 px-2.5 py-0.5 text-[10.5px] font-semibold tracking-wide text-white shadow-sm shadow-primary-600/30">
                                Most Popular
                            </span>
                        @endif

                        @if (! $available)
                            <span class="absolute -top-2.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-slate-800 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-white">
                                Coming Soon
                            </span>
                        @endif

                        @if ($available)
                            <input
                                type="radio"
                                name="plan_id"
                                value="{{ $plan->id }}"
                                x-model.number="planId"
                                class="sr-only"
                            >
                        @endif

                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-900/30 dark:ring-primary-800">
                            @if ($plan->key->value === 'basic')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12l16-8-6 16-2.5-6.5L4 12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                </svg>
                            @elseif ($plan->key->value === 'standard')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M3 12h18M12 3c2.5 2.5 2.5 15.5 0 18M12 3c-2.5 2.5-2.5 15.5 0 18" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            @else
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 8l4 3 5-6 5 6 4-3-2 10H5L3 8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                </svg>
                            @endif
                        </span>

                        <h3 class="wz-title mt-3.5">{{ $plan->name }}</h3>
                        <small class="wz-lead">{{ $plan->tagline }}</small>

                        <div class="wz-panel mt-4 px-4 py-3">
                            {{-- Basic and Standard are both priced per pupil
                                 now, the same sentence, at each plan's own
                                 rate, read from the plan record rather than
                                 written here. Standard's ₦200,000-a-term fee
                                 and its monthly/per-term toggle are gone with
                                 it; there is one way to be billed. --}}
                            @if (! $available)
                                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Coming Soon</p>
                                <small class="mt-0.5 block text-[11px] leading-[1.45] text-slate-500 dark:text-gray-400">Not yet available for subscription</small>
                            @elseif ($plan->price_per_student_per_term)
                                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">&#8358;{{ number_format($plan->price_per_student_per_term, 0) }}</p>
                                <small class="mt-0.5 block text-[11px] leading-[1.45] text-slate-500 dark:text-gray-400">Per Student / Per Term &mdash; Billed based on number of students</small>
                            @else
                                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Custom Pricing</p>
                                <small class="mt-0.5 block text-[11px] leading-[1.45] text-slate-500 dark:text-gray-400">Contact Sales for Pricing</small>
                            @endif
                        </div>

                        <ul class="mt-4 flex-1 space-y-1.5">
                            @foreach ($plan->features as $feature)
                                <li class="flex items-start gap-2 text-[12.5px] leading-[1.5] text-slate-600 dark:text-gray-300">
                                    <svg class="mt-[3px] h-3.5 w-3.5 shrink-0 text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>

                        @if ($available)
                            <div
                                class="mt-5 w-full rounded-lg border px-4 py-2 text-center text-[13px] font-semibold transition"
                                :class="planId === {{ $plan->id }} ? 'border-primary-500 bg-primary-500 text-white shadow-sm shadow-primary-500/25' : 'border-slate-200 text-slate-700 dark:border-gray-600 dark:text-gray-200'"
                            >
                                <span x-show="planId !== {{ $plan->id }}">Choose {{ $plan->name }}</span>
                                <span x-show="planId === {{ $plan->id }}" x-cloak><i class="fa-solid fa-check mr-1 text-[11px]" aria-hidden="true"></i>Selected</span>
                            </div>
                        @else
                            {{-- A real disabled button, not a styled div: it
                                 cannot be clicked, cannot be focused, and is
                                 announced as unavailable. --}}
                            <button
                                type="button"
                                disabled
                                class="btn mt-5 w-full cursor-not-allowed border border-slate-200 bg-slate-100 text-center font-semibold text-slate-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400"
                            >
                                Coming Soon
                            </button>
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-between gap-3 sm:justify-end">
                <small class="text-[12px] text-slate-500 sm:hidden" x-show="!planId">Choose a plan to continue</small>
                <button
                    type="submit"
                    :disabled="!planId"
                    class="btn wz-btn ml-auto sm:ml-0"
                >
                    Continue
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
