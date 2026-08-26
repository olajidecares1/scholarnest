<x-auth-layout :title="'Create a new password - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-500">
                <i class="fa-solid fa-key text-xl"></i>
            </span>
        </div>

        <h1 class="mt-5 text-center text-2xl font-bold tracking-tight text-gray-900">Create a new password</h1>
        <p class="mx-auto mt-2 max-w-xs text-center text-sm leading-relaxed text-gray-500">
            Choose a password you haven&rsquo;t used before. You&rsquo;ll use it to sign in from now on.
        </p>

        <form method="POST" action="{{ route('admin.password-reset.complete', $token) }}" class="mt-7">
            @csrf

            {{-- The same component the School Admin and Super Admin both get:
                 one implementation of the rules, the meter and the enabling of
                 the button, rather than two that drift apart. --}}
            <x-password-strength-field
                label="New Password"
                confirm-label="Confirm New Password"
                submit-label="Reset Password"
            />
        </form>

        <p class="mt-6 border-t border-gray-100 pt-5 text-center text-xs text-gray-400">
            <i class="fa-solid fa-lock"></i>
            Your password is encrypted and never visible to anyone at EduNest.
        </p>
    </x-auth-card>
</x-auth-layout>
