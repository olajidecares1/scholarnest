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
                    <label
                        class="relative flex cursor-pointer flex-col rounded-[8px] border-2 bg-white p-6 shadow-sm transition"
                        :class="planId === {{ $plan->id }} ? 'border-primary-500 ring-2 ring-primary-100' : 'border-gray-200 hover:border-gray-300'"
                    >
                        @if ($plan->is_popular)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary-500 px-3 py-1 text-xs font-bold text-white">
                                Most Popular
                            </span>
                        @endif

                        <input
                            type="radio"
                            name="plan_id"
                            value="{{ $plan->id }}"
                            x-model.number="planId"
                            class="sr-only"
                        >

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
                            @if ($plan->has_custom_pricing)
                                <p class="text-xl font-extrabold text-gray-900">Custom Pricing</p>
                                <p class="text-xs text-gray-500">Contact Sales for Pricing</p>
                            @elseif ($plan->key->value === 'basic')
                                <p class="text-xl font-extrabold text-gray-900">&#8358;{{ number_format($plan->price_per_student_per_term, 0) }}</p>
                                <p class="text-xs text-gray-500">Per Student / Per Term &mdash; Billed based on number of students</p>
                            @else
                                <div x-show="planId === {{ $plan->id }}" x-cloak class="mb-3 flex rounded-[8px] bg-white p-1 text-xs font-semibold">
                                    <button type="button" @click="billingCycle = 'monthly'" :class="billingCycle === 'monthly' ? 'bg-primary-500 text-white' : 'text-gray-500'" class="flex-1 rounded-[8px] px-3 py-1.5">Monthly</button>
                                    <button type="button" @click="billingCycle = 'per_term'" :class="billingCycle === 'per_term' ? 'bg-primary-500 text-white' : 'text-gray-500'" class="flex-1 rounded-[8px] px-3 py-1.5">Per Term</button>
                                </div>
                                <input type="hidden" name="billing_cycle" :value="planId === {{ $plan->id }} ? billingCycle : ''">

                                <template x-if="billingCycle === 'monthly'">
                                    <div>
                                        <p class="text-xl font-extrabold text-gray-900">&#8358;{{ number_format($plan->price_monthly, 0) }}</p>
                                        <p class="text-xs text-gray-500">Per Month</p>
                                    </div>
                                </template>
                                <template x-if="billingCycle === 'per_term'">
                                    <div>
                                        <p class="text-xl font-extrabold text-gray-900">&#8358;{{ number_format($plan->price_per_term, 0) }}</p>
                                        <p class="text-xs text-gray-500">Per Term</p>
                                    </div>
                                </template>
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

                        <div
                            class="mt-6 w-full rounded-[8px] border-2 px-4 py-2.5 text-center text-sm font-semibold transition"
                            :class="planId === {{ $plan->id }} ? 'border-primary-500 bg-primary-500 text-white' : 'border-gray-200 text-gray-700'"
                        >
                            <span x-show="planId !== {{ $plan->id }}">Choose {{ $plan->name }}</span>
                            <span x-show="planId === {{ $plan->id }}" x-cloak>Selected</span>
                        </div>
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
