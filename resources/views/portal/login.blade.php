<x-auth-layout :simple="true" :header="false" logo-placement="above" :title="$school ? $school->name.' Portal' : 'AkademicNest Portal'">
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
                <h1 class="text-lg font-bold text-gray-900">AkademicNest Portal</h1>
            @endif
            <small class="block text-xs font-semibold uppercase tracking-wide text-primary-500">Sign in to continue</small>
        </div>

        <x-auth-session-status class="mt-4 text-center" :status="session('status')" />

        <form method="POST" action="{{ url()->current() }}" class="mt-6 space-y-2">
            @csrf

            <div>
                <span class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-gray-500">I am signing in as</span>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['web' => 'School Admin', 'staff' => 'Staff', 'student' => 'Student', 'guardian' => 'Parent / Guardian'] as $value => $label)
                        <label class="btn flex cursor-pointer items-center justify-center rounded-[8px] border border-gray-300 px-3 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-150 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700">
                            <input type="radio" name="role" value="{{ $value }}" class="sr-only" {{ old('role', 'web') === $value ? 'checked' : '' }} required>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- No school code. The school is found from the account; only when
                 the same details open accounts at more than one school is the
                 person asked to pick, from those schools alone. --}}
            @php($schoolChoices = $school ? null : session(\App\Http\Requests\Portal\LoginRequest::CHOICES_SESSION_KEY))
            @if ($schoolChoices && ($errors->has('school') || old('school')))
                <fieldset class="rounded-[8px] border border-primary-200 bg-primary-50/60 p-3">
                    <legend class="px-1 text-[11px] font-bold uppercase tracking-wide text-primary-700">Choose your school</legend>
                    @error('school')<p class="mb-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    <div class="space-y-2">
                        @foreach ($schoolChoices as $key => $name)
                            <label class="btn flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-300 bg-white px-3 py-2.5 text-sm font-semibold text-gray-700 has-[:checked]:border-primary-500 has-[:checked]:text-primary-700">
                                <input type="radio" name="school" value="{{ $key }}" class="text-primary-600" {{ old('school') === $key ? 'checked' : '' }} required>
                                {{ $name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

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
                class="btn flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
            >
                Sign In
                <i class="fa-solid fa-arrow-right text-[14px] leading-none" aria-hidden="true"></i>
            </button>
        </form>
    </x-auth-card>
</x-auth-layout>
