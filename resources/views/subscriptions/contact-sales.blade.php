<x-dashboard-layout page-title="Exclusive Plan" page-subtitle="Let's talk about what your school needs.">
    <div class="mx-auto max-w-2xl">
        <x-auth-card class="!max-w-none text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 8l4 3 5-6 5 6 4-3-2 10H5L3 8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </span>

            <h2 class="mt-4 text-xl font-bold text-gray-900">{{ $plan->name }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $plan->tagline }}</p>

            <p class="mt-4 text-sm text-gray-600">
                The Exclusive plan is custom-priced around your school&rsquo;s needs, including a custom domain,
                DNS management, and white-label branding. Our team will reach out to put together a plan that fits.
            </p>

            <ul class="mx-auto mt-6 max-w-sm space-y-2 text-left">
                @foreach ($plan->features as $feature)
                    <li class="flex items-start gap-2 text-sm text-gray-700">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a
                    href="mailto:sales@edunest.com?subject=Exclusive%20Plan%20Enquiry"
                    class="inline-flex items-center gap-2 rounded-[2px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                >
                    Email Our Sales Team
                </a>
                <a href="{{ route('subscriptions.choose-plan') }}" class="text-sm font-semibold text-gray-600 hover:text-gray-900">&larr; Back to Plans</a>
            </div>
        </x-auth-card>
    </div>
</x-dashboard-layout>
