<x-auth-layout
    :title="'Sign in - ' . config('app.name')"
    auth-question="Don't have an account?"
    auth-link-label="Sign Up"
    :auth-link-route="route('register')"
>
    <x-slot:left>
        <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-700">
            School Sign In
        </span>

        <h1 class="mt-4 text-3xl font-extrabold leading-tight text-gray-900 sm:text-4xl">
            Welcome Back to <span class="text-primary-500">Your School</span>
        </h1>

        <p class="mt-4 max-w-md text-gray-600">
            Sign in to manage students, staff, attendance, results and more &mdash; all in one place.
        </p>

        <x-auth-panel-features />
        <x-auth-panel-illustration />
    </x-slot:left>

    <div class="mx-auto w-full max-w-md rounded-[5px] border border-gray-200 bg-white p-6 shadow-2xl shadow-primary-900/10 ring-1 ring-black/5 sm:p-8 lg:rounded-[10px]">
        <h2 class="text-xl font-bold text-gray-900">Sign In to Your Account</h2>
        <p class="mt-1 text-sm text-gray-600">Choose your preferred method to continue</p>

        <x-auth-session-status class="mt-4" :status="session('status')" />

        <div class="mt-6">
            <x-auth-social-buttons />
        </div>

        <div class="my-6 flex items-center gap-3">
            <span class="h-px flex-1 bg-gray-200"></span>
            <span class="text-xs font-bold uppercase text-gray-400">or</span>
            <span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <x-auth-email-input autofocus />

            <x-auth-password-input
                id="password"
                name="password"
                label="Password"
                placeholder="Enter your password"
                autocomplete="current-password"
            />

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        name="remember"
                        class="rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500"
                    />
                    Remember me
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-primary-500 hover:text-primary-600">
                        Forgot password?
                    </a>
                @endif
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 lg:rounded-[10px]"
            >
                Sign In
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <x-auth-security-note />
    </div>
</x-auth-layout>
