<x-auth-layout :simple="true" :header="false" :title="'School Admin Portal · '.$school->name" :background="$background" :school="$school" pwaPortal="admin">
    <x-auth-card>
        <div class="text-center">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="mx-auto h-20 w-20 rounded-[10px] object-cover shadow-md">
            @else
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-[10px] bg-primary-600 text-xl text-white shadow-md"><i class="fa-solid fa-school"></i></span>
            @endif
            <h1 class="mt-3 text-lg font-bold text-gray-900">{{ $school->name }}</h1>
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-500">School Admin Portal</p>
        </div>

        <x-auth-session-status class="mt-4 text-center" :status="session('status')" />

        <form method="POST" action="{{ url()->current() }}" class="mt-6 space-y-2">
            @csrf

            <x-text-field
                id="login"
                name="login"
                label="Email or Username"
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

            {{-- Forgot-password lived on the shared sign-in page, which no
                 longer exists. This is where School Admins actually sign in,
                 so the link belongs here, without it they would have no way
                 to reset a password at all. Deliberately absent from the
                 student, staff and guardian portals, where a reset is the
                 school's job rather than self-service. --}}
            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="remember" class="text-primary-500">
                    Remember me
                </label>

                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-primary-500 hover:text-primary-600">
                    Forgot password?
                </a>
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>

            <p class="text-center text-xs text-gray-500">
                <a href="{{ $school->publicUrl('portal.index') }}" class="font-semibold text-primary-500 hover:text-primary-600">&larr; Back to Portal</a>
            </p>
        </form>
    </x-auth-card>
</x-auth-layout>
