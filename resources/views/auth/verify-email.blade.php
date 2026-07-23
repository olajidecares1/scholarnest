<x-auth-layout :title="'Verify Email - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                    <path d="M3 6l9 7 9-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Verify Your Email</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            Thanks for signing up! Before getting started, click the verification link we just emailed to you.
            If you didn&rsquo;t receive it, we can send another one.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="mt-4 rounded-[5px] bg-primary-50 p-3 text-center text-sm font-medium text-primary-700 lg:rounded-[10px]">
                A new verification link has been sent to the email address you provided during registration.
            </div>
        @endif

        <div class="mt-6 flex flex-col gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 lg:rounded-[10px]"
                >
                    Resend Verification Email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="w-full text-center text-sm font-semibold text-gray-600 hover:text-gray-900"
                >
                    Log Out
                </button>
            </form>
        </div>
    </x-auth-card>
</x-auth-layout>
