<x-auth-layout :simple="true" :header="false" :title="$school ? $school->name.' Portal' : 'AkademicNest Portal'">
    <x-auth-card>
        <div class="text-center">
            @if ($school)
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="mx-auto h-20 w-20 rounded-[10px] object-cover shadow-md">
                @else
                    <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-[10px] bg-primary-600 text-lg font-extrabold text-white shadow-md">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                @endif
                <h1 class="mt-3 text-lg font-bold text-gray-900">{{ $school->name }}</h1>
            @else
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-[10px] bg-primary-600 text-lg font-extrabold text-white shadow-md">S</span>
                <h1 class="mt-3 text-lg font-bold text-gray-900">AkademicNest Portal</h1>
            @endif
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-500">Sign in to continue</p>
        </div>

        <x-auth-session-status class="mt-4 text-center" :status="session('status')" />

        <form method="POST" action="{{ url()->current() }}" class="mt-6 space-y-2">
            @csrf

            <div>
                <span class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-gray-500">I am signing in as</span>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['web' => 'School Admin', 'staff' => 'Staff', 'student' => 'Student', 'guardian' => 'Parent / Guardian'] as $value => $label)
                        <label class="flex cursor-pointer items-center justify-center rounded-[8px] border border-gray-300 px-3 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-150 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700">
                            <input type="radio" name="role" value="{{ $value }}" class="sr-only" {{ old('role', 'web') === $value ? 'checked' : '' }} required>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            @unless ($school)
                <x-text-field
                    id="school_code"
                    name="school_code"
                    label="School Code"
                    icon="M4 6.5a1.5 1.5 0 011.5-1.5h13A1.5 1.5 0 0120 6.5v11a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 17.5v-11z"
                    helper="Ask your school office for your school code."
                    :value="old('school_code')"
                    required
                    error-bag="default"
                />
            @endunless

            <x-text-field
                id="login"
                name="login"
                label="Email / Admission No. / Staff No. / Username"
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
        </form>
    </x-auth-card>
</x-auth-layout>
