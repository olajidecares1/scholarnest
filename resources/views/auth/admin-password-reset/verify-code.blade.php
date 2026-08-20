<x-auth-layout :title="'Verify Code - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4" stroke="currentColor" stroke-width="1.5" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Enter Your Verification Code</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            We sent a 6-digit verification code to your email. Enter it below to continue.
        </p>

        <form method="POST" action="{{ route('admin.password-reset.verify-code', $token) }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="code" class="mb-1.5 block text-sm font-semibold text-gray-700">Verification Code</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    class="block w-full rounded-[8px] border border-gray-300 px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="&middot;&middot;&middot;&middot;&middot;&middot;"
                >
                @error('code')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Verify Code
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="{{ route('admin.password-reset.request') }}" class="font-semibold text-primary-500 hover:text-primary-600">Request a new link</a>
        </p>
    </x-auth-card>
</x-auth-layout>
