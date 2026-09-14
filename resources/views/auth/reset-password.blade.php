<x-auth-layout :title="'Reset Password | ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="5" y="10" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                    <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Reset Your Password</h2>
        <p class="mt-1 text-center text-sm text-gray-600">Enter the code from your email, then choose a new password.</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-2">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-auth-email-input :value="old('email', $request->email)" />

            {{-- The second half of the reset. The link proves possession of the
                 URL; this proves the email itself was read, so a link that
                 leaks through a referrer header or a shared inbox is not on its
                 own enough to take the account.

                 inputmode="numeric" so a phone offers the number pad, and
                 autocomplete="one-time-code" so the code can be filled from the
                 notification rather than retyped. --}}
            <div>
                <label for="code" class="field-label">Verification Code</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    required
                    autofocus
                    placeholder="6-digit code from your email"
                    value="{{ old('code') }}"
                    class="mt-1 w-full tracking-[0.35em] @error('code') field-invalid @enderror"
                >
                <x-input-error :messages="$errors->get('code')" class="mt-1" />
            </div>

            <x-auth-password-input
                id="password"
                name="password"
                label="New Password"
                placeholder="Create a strong password"
                autocomplete="new-password"
            />

            <x-auth-password-input
                id="password_confirmation"
                name="password_confirmation"
                label="Confirm New Password"
                placeholder="Confirm your new password"
                autocomplete="new-password"
            />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Reset Password
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>
    </x-auth-card>
</x-auth-layout>
