<x-auth-layout
    :title="'Register your school - ' . config('app.name')"
    auth-question="Already have an account?"
    auth-link-label="Sign In"
    :auth-link-route="route('login')"
>
    <x-slot:left>
        <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-700">
            School Registration
        </span>

        <h1 class="mt-4 text-3xl font-extrabold leading-tight text-gray-900 sm:text-4xl">
            Create Your <span class="text-primary-500">School Account</span>
        </h1>

        <p class="mt-4 max-w-md text-gray-600">
            Register your school on EduNest to manage students, staff, attendance, results and more &mdash; all in one place.
        </p>

        <x-auth-panel-features />
        <x-auth-panel-illustration />
    </x-slot:left>

    <x-auth-card>
        <h2 class="text-xl font-bold text-gray-900">Create Your School Account</h2>
        <p class="mt-1 text-sm text-gray-600">Choose your preferred method to get started</p>

        <div class="mt-6">
            <x-auth-social-buttons />
        </div>

        <div class="my-6 flex items-center gap-3">
            <span class="h-px flex-1 bg-gray-200"></span>
            <span class="text-xs font-bold uppercase text-gray-400">or</span>
            <span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="school_name" value="School Name" />
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-primary-500">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 21h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M5 21V10M19 21V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M3 10l9-6 9 6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <path d="M8 10v11M12 10v11M16 10v11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <x-text-input
                        id="school_name"
                        name="school_name"
                        type="text"
                        class="pl-10"
                        :value="old('school_name')"
                        required
                        autofocus
                        autocomplete="organization"
                        placeholder="Enter your school name"
                    />
                </div>
                <x-input-error :messages="$errors->get('school_name')" class="mt-2" />
            </div>

            <x-auth-email-input />

            <x-auth-password-input
                id="password"
                name="password"
                label="Password"
                placeholder="Create a strong password"
                autocomplete="new-password"
            />

            <x-auth-password-input
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm Password"
                placeholder="Confirm your password"
                autocomplete="new-password"
            />

            <div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        name="terms"
                        required
                        class="mt-0.5 rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500"
                    />
                    <span>
                        I agree to the
                        <a href="#" class="font-semibold text-primary-500 hover:text-primary-600">Terms of Service</a>
                        and
                        <a href="#" class="font-semibold text-primary-500 hover:text-primary-600">Privacy Policy</a>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('terms')" class="mt-2" />
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 lg:rounded-[10px]"
            >
                Create Account
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <x-auth-security-note />
    </x-auth-card>
</x-auth-layout>
