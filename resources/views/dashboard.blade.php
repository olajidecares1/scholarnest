@php
    $school = auth()->user()->school;
    $subscription = $school->activeSubscription();
@endphp

<x-dashboard-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.auth()->user()->name.'.'">
    <div class="mx-auto max-w-4xl">
        @if (! $subscription)
            <div class="rounded-[5px] border border-primary-200 bg-primary-50 p-6 text-center lg:rounded-[10px]">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                        <path d="M3 10h18" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </span>
                <h2 class="mt-3 text-lg font-bold text-gray-900">You don&rsquo;t have an active subscription yet</h2>
                <p class="mt-1 text-sm text-gray-600">Choose a plan to unlock EduNest for {{ $school->name }}.</p>
                <a
                    href="{{ route('subscriptions.choose-plan') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-[5px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 lg:rounded-[10px]"
                >
                    Choose a Plan
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        @else
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">{{ $subscription->plan->name }}</h2>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-bold uppercase',
                        'bg-green-100 text-green-700' => $subscription->status === \App\Enums\SubscriptionStatus::Active,
                        'bg-amber-100 text-amber-700' => $subscription->status === \App\Enums\SubscriptionStatus::PendingVerification,
                        'bg-gray-100 text-gray-600' => $subscription->status === \App\Enums\SubscriptionStatus::PendingPayment,
                    ])>
                        {{ $subscription->status->label() }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-600">Reference: {{ $subscription->reference }}</p>
                <p class="mt-4 text-sm text-gray-600">
                    @if ($subscription->status === \App\Enums\SubscriptionStatus::PendingVerification)
                        Your payment is under review. We&rsquo;ll email you once it&rsquo;s confirmed.
                    @elseif ($subscription->status === \App\Enums\SubscriptionStatus::Active)
                        Your subscription is active. Thanks for being part of EduNest!
                    @else
                        Your subscription is awaiting payment.
                    @endif
                </p>
                <a href="{{ route('subscriptions.confirmation', $subscription) }}" class="mt-4 inline-block text-sm font-semibold text-primary-500 hover:text-primary-600">
                    View subscription details &rarr;
                </a>
            </div>
        @endif
    </div>
</x-dashboard-layout>
