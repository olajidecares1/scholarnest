<x-auth-layout :title="'Link Expired - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-red-100 text-red-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                    <path d="M12 8v5M12 15.5h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">This Link Is Invalid or Has Expired</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            Password reset links can only be used once and expire after a short time. Please request a new one.
        </p>

        <a
            href="{{ route('admin.password-reset.request') }}"
            class="mt-6 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
        >
            Request a New Link
        </a>

        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="{{ route('login') }}" class="font-semibold text-primary-500 hover:text-primary-600">&larr; Back to Sign In</a>
        </p>
    </x-auth-card>
</x-auth-layout>
