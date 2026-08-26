<x-auth-layout :simple="true" :title="'Staff Portal · '.$school->name">
    <x-auth-card>
        <div class="text-center">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="mx-auto h-14 w-14 rounded-[10px] object-cover shadow-md">
            @else
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-[10px] bg-primary-600 text-lg font-extrabold text-white shadow-md">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
            @endif
            <h1 class="mt-3 text-lg font-bold text-gray-900">{{ $school->name }}</h1>
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-500">Staff Portal</p>
        </div>

        <x-auth-session-status class="mt-4 text-center" :status="session('status')" />

        <form method="POST" action="{{ url()->current() }}" class="mt-6 space-y-2">
            @csrf

            <x-text-field
                id="login"
                name="login"
                label="Staff ID or Email"
                icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                :value="old('login')"
                required
                autofocus
                autocomplete="username"
                error-bag="default"
            />

            <x-auth-password-input
                id="password"
                name="password"
                label="Password"
                placeholder="Enter your password"
                autocomplete="current-password"
            />

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="remember" class="text-primary-500">
                Remember me
            </label>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Sign In
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>

            {{-- Staff accounts have no self-service password recovery by design.
                 Only a School Admin may reset a staff password. See
                 docs/PASSWORD-RESET-POLICY.md. --}}
            <p class="text-center text-xs text-gray-500">Forgot your password? Please contact your School Admin to reset your account.</p>
            <p class="text-center text-xs text-gray-500">Don't have your login details? Ask your school office for your Staff ID and portal password.</p>
            <p class="text-center text-xs text-gray-500">
                <a href="{{ $school->publicUrl('portal.index') }}" class="font-semibold text-primary-500 hover:text-primary-600">&larr; Back to Portal</a>
            </p>
        </form>
    </x-auth-card>
</x-auth-layout>
