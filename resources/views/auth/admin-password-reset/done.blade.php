<x-auth-layout :title="'Password reset successful - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="text-center">
            {{-- The tick draws itself once on arrival. Deliberately a single
                 short animation rather than anything looping: it marks the
                 moment the thing succeeded and then gets out of the way. --}}
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-green-600">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" class="reset-done-ring" />
                    <path d="M7.5 12.5l3 3 6-6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="reset-done-tick" />
                </svg>
            </span>

            <h1 class="mt-5 text-2xl font-bold tracking-tight text-gray-900">Password reset successful</h1>

            <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-gray-500">
                Your password has been successfully updated. You can now sign in with your new password.
            </p>

            {{-- Straight to the door this particular person uses: their own
                 school's portal if they have one, the platform front door if
                 they are a Super Admin. Worked out before the reset completed,
                 while the account was still resolvable. --}}
            <a
                href="{{ $signInUrl }}"
                class="mt-7 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Continue to Login <i class="fa-solid fa-arrow-right"></i>
            </a>

            <p class="mt-6 border-t border-gray-100 pt-5 text-xs text-gray-400">
                <i class="fa-solid fa-shield-halved"></i>
                If you didn&rsquo;t make this change, contact EduNest support immediately.
            </p>
        </div>
    </x-auth-card>
</x-auth-layout>
