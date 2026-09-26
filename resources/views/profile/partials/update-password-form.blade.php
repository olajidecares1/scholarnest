<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <small class="block mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </small>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-2">
        @csrf
        @method('put')

        <x-password-field
            id="update_password_current_password"
            name="current_password"
            label="Current Password"
            helper="Enter your existing password to confirm this change."
            autocomplete="current-password"
            error-bag="updatePassword"
        />

        <x-password-field
            id="update_password_password"
            name="password"
            label="New Password"
            helper="At least 8 characters, with a mix of letters and numbers."
            autocomplete="new-password"
            error-bag="updatePassword"
        />

        <x-password-field
            id="update_password_password_confirmation"
            name="password_confirmation"
            label="Confirm Password"
            helper="Re-enter the new password exactly as above."
            autocomplete="new-password"
            error-bag="updatePassword"
        />

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <small
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="block text-sm text-gray-600"
                >{{ __('Saved.') }}</small>
            @endif
        </div>
    </form>
</section>
