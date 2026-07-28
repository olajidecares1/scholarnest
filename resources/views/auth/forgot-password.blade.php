<x-auth-layout :title="'Forgot Password - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="10" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                    <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Forgot Your Password?</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            No problem. Enter your email address and we&rsquo;ll send you a link to reset it.
        </p>

        <x-auth-session-status class="mt-4" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <x-auth-email-input autofocus />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Send Reset Link
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="{{ route('login') }}" class="font-semibold text-primary-500 hover:text-primary-600">&larr; Back to Sign In</a>
        </p>
    </x-auth-card>
</x-auth-layout>
