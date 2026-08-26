<x-auth-layout :title="'Reset your password - ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-500">
                <i class="fa-solid fa-unlock-keyhole text-xl"></i>
            </span>
        </div>

        <h1 class="mt-5 text-center text-2xl font-bold tracking-tight text-gray-900">Reset your password</h1>
        <p class="mx-auto mt-2 max-w-xs text-center text-sm leading-relaxed text-gray-500">
            Enter the email address on your account and we&rsquo;ll send you a reset link and verification code.
        </p>

        @if (session('status'))
            {{-- Deliberately the same message whether or not the address
                 matched an account: a different one would let anybody use this
                 form to discover which emails are registered. --}}
            <div class="mt-6 flex items-start gap-2.5 rounded-[8px] bg-green-50 p-3.5 text-xs leading-relaxed text-green-700">
                <i class="fa-solid fa-circle-check mt-0.5"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password-reset.send') }}" class="mt-6 space-y-2">
            @csrf

            <div>
                <label for="email" class="field-label mb-1">Email Address</label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                        <i class="fa-solid fa-envelope text-sm"></i>
                    </span>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="you@yourschool.com"
                        class="block w-full pl-10 pr-3"
                    >
                </div>

                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                <i class="fa-solid fa-paper-plane"></i> Send Reset Code
            </button>
        </form>

        <p class="mt-6 border-t border-gray-100 pt-5 text-center text-xs text-gray-400">
            <i class="fa-solid fa-clock"></i>
            The link and code expire after 30 minutes and can only be used once.
        </p>
    </x-auth-card>
</x-auth-layout>
