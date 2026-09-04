<x-dashboard-layout page-title="Choose Your Plan" page-subtitle="Select a subscription plan that best fits your school's needs.">
    <div class="mx-auto max-w-6xl space-y-6">
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

            <x-input-error :messages="$errors->get('plan_id')" class="mb-4" />
            <x-input-error :messages="$errors->get('billing_cycle')" class="mb-4" />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php $available = $plan->key->isAvailableToSubscribe(); @endphp

                    <label
                        @class([
                            'relative flex flex-col rounded-[8px] border-2 bg-white p-6 shadow-sm transition',
                            'cursor-pointer' => $available,
                            // Coming soon: visibly out of reach, and with no
                            // radio inside it to select. The server refuses it
                            // too - see PlanKey::isAvailableToSubscribe.
                            'cursor-not-allowed border-gray-200 opacity-60' => ! $available,
                        ])
                        @if ($available)
                            :class="planId === {{ $plan->id }} ? 'border-primary-500 ring-2 ring-primary-100' : 'border-gray-200 hover:border-gray-300'"
                        @endif
                    >
                        @if ($plan->is_popular && $available)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary-500 px-3 py-1 text-xs font-bold text-white">
                                Most Popular
                            </span>
                        @endif

                        @if (! $available)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-gray-800 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">
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

                        <span class="flex h-12 w-12 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                            @if ($plan->key->value === 'basic')
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12l16-8-6 16-2.5-6.5L4 12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                </svg>
                            @elseif ($plan->key->value === 'standard')
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                                    <path d="M3 12h18M12 3c2.5 2.5 2.5 15.5 0 18M12 3c-2.5 2.5-2.5 15.5 0 18" stroke="currentColor" stroke-width="1.5" />
                                </svg>
                            @else
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 8l4 3 5-6 5 6 4-3-2 10H5L3 8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                                </svg>
                            @endif
                        </span>

                        <h3 class="mt-4 text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $plan->tagline }}</p>

                        <div class="mt-4 rounded-[5px] bg-gray-50 p-4 lg:rounded-[10px]">
                            {{-- Basic and Standard are both priced per pupil
                                 now - the same sentence, at each plan's own
                                 rate, read from the plan record rather than
                                 written here. Standard's ₦200,000-a-term fee
                                 and its monthly/per-term toggle are gone with
                                 it; there is one way to be billed. --}}
                            @if (! $available)
                                <p class="text-xl font-extrabold text-gray-900">Coming Soon</p>
                                <p class="text-xs text-gray-500">Not yet available for subscription</p>
                            @elseif ($plan->price_per_student_per_term)
                                <p class="text-xl font-extrabold text-gray-900">&#8358;{{ number_format($plan->price_per_student_per_term, 0) }}</p>
                                <p class="text-xs text-gray-500">Per Student / Per Term &mdash; Billed based on number of students</p>
                            @else
                                <p class="text-xl font-extrabold text-gray-900">Custom Pricing</p>
                                <p class="text-xs text-gray-500">Contact Sales for Pricing</p>
                            @endif
                        </div>

                        <ul class="mt-4 flex-1 space-y-2">
                            @foreach ($plan->features as $feature)
                                <li class="flex items-start gap-2 text-sm text-gray-700">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>

                        @if ($available)
                            <div
                                class="mt-6 w-full rounded-[8px] border-2 px-4 py-2.5 text-center text-sm font-semibold transition"
                                :class="planId === {{ $plan->id }} ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-200 text-gray-700'"
                            >
                                <span x-show="planId !== {{ $plan->id }}">Choose {{ $plan->name }}</span>
                                <span x-show="planId === {{ $plan->id }}" x-cloak>Selected</span>
                            </div>
                        @else
                            {{-- A real disabled button, not a styled div: it
                                 cannot be clicked, cannot be focused, and is
                                 announced as unavailable. --}}
                            <button
                                type="button"
                                disabled
                                class="mt-6 w-full cursor-not-allowed rounded-[8px] border-2 border-gray-200 bg-gray-100 px-4 py-2.5 text-center text-sm font-semibold text-gray-500"
                            >
                                Coming Soon
                            </button>
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="mt-8 flex justify-end">
                <button
                    type="submit"
                    :disabled="!planId"
                    :class="planId ? 'bg-primary-500 hover:bg-primary-600' : 'cursor-not-allowed bg-gray-300'"
                    class="flex items-center gap-2 rounded-[8px] px-6 py-3 text-sm font-bold text-white shadow-md transition"
                >
                    Continue
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
