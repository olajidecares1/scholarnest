<x-dashboard-layout page-title="Exclusive Plan" page-subtitle="Let's talk about what your school needs.">
    <div class="wz-shell">
        <div class="wz-card text-center">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-900/30 dark:ring-primary-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 8l4 3 5-6 5 6 4-3-2 10H5L3 8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
            </span>

            <h2 class="wz-title mt-4">{{ $plan->name }}</h2>
            <small class="wz-lead">{{ $plan->tagline }}</small>

            <small class="mx-auto mt-3 block max-w-md text-[12.5px] leading-[1.6] text-slate-500 dark:text-gray-400">
                The Exclusive plan is custom-priced around your school&rsquo;s needs, including a custom domain,
                DNS management, and white-label branding. Our team will reach out to put together a plan that fits.
            </small>

            <ul class="wz-panel mx-auto mt-5 max-w-sm space-y-2 px-4 py-3.5 text-left">
                @foreach ($plan->features as $feature)
                    <li class="flex items-start gap-2 text-[12.5px] text-slate-700 dark:text-gray-300">
                        <svg class="mt-[3px] h-3.5 w-3.5 shrink-0 text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row-reverse sm:gap-5">
                <a
                    {{-- The confirmed mailbox, with the subject kept so these
                         can still be filtered. This read "sales@", an address
                         nobody has said exists, on the page selling the most
                         expensive plan. Change it back once that mailbox does. --}}
                    href="mailto:{{ \App\Models\Setting::supportEmail() }}?subject=Exclusive%20Plan%20Enquiry"
                    class="btn wz-btn"
                >
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email Our Sales Team
                </a>
                <a href="{{ route('subscriptions.choose-plan') }}" class="wz-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Plans</a>
            </div>
        </div>
    </div>
</x-dashboard-layout>
