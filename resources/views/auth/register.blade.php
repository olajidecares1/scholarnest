<x-auth-layout
    :title="'Register your school - ' . config('app.name')"
    auth-question="Already have an account?"
    auth-link-label="Sign In"
    :auth-link-route="route('login')"
>
    <x-slot:left>
        <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary-600">
            School Registration
        </span>

        <h1 class="mt-4 text-3xl font-bold leading-tight text-gray-900 sm:text-4xl">
            Create Your <span class="text-primary-500">School Account</span>
        </h1>

        <p class="mt-4 max-w-md text-gray-600">
            Register your school on EduNest to manage students, staff, attendance, results and more &mdash; all in one place.
        </p>

        <ul class="mt-8 hidden space-y-6 lg:block">
            <li class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[5px] bg-primary-50 text-primary-500 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                        <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-gray-900">Secure &amp; Protected</p>
                    <p class="text-sm text-gray-600">Enterprise-grade security keeps your school&rsquo;s data safe.</p>
                </div>
            </li>
            <li class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[5px] bg-primary-50 text-primary-500 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="7" width="16" height="14" rx="1" stroke="currentColor" stroke-width="1.5" />
                        <path d="M9 3h6v4H9z" stroke="currentColor" stroke-width="1.5" />
                        <path d="M8 11h2M14 11h2M8 15h2M14 15h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-gray-900">Manage Everything</p>
                    <p class="text-sm text-gray-600">Students, staff, attendance, results and more in one dashboard.</p>
                </div>
            </li>
            <li class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[5px] bg-primary-50 text-primary-500 lg:rounded-[10px]">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 20V10M10 20V4M16 20v-7M20 20v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-gray-900">Powerful Dashboard</p>
                    <p class="text-sm text-gray-600">Real-time insights and analytics to help your school thrive.</p>
                </div>
            </li>
        </ul>

        <div class="mt-10 hidden lg:block">
            <svg viewBox="0 0 360 220" class="w-full max-w-md" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 220c0-35 45-55 100-55s75 22 140 22 90-30 120-12v45H0v0z" fill="#1877F2" />
                <rect x="120" y="90" width="120" height="100" rx="4" fill="#DCEBFE" />
                <rect x="120" y="90" width="120" height="14" fill="#1877F2" />
                <polygon points="112,90 180,55 248,90" fill="#1259BD" />
                <rect x="172" y="60" width="16" height="22" fill="#1259BD" />
                <rect x="176" y="42" width="4" height="20" fill="#1259BD" />
                <polygon points="178,38 178,48 190,43" fill="#1877F2" />
                <circle cx="180" cy="120" r="14" fill="#ffffff" stroke="#1259BD" stroke-width="2" />
                <line x1="180" y1="120" x2="180" y2="111" stroke="#1259BD" stroke-width="2" stroke-linecap="round" />
                <line x1="180" y1="120" x2="186" y2="120" stroke="#1259BD" stroke-width="2" stroke-linecap="round" />
                <rect x="130" y="145" width="18" height="18" fill="#ffffff" />
                <rect x="212" y="145" width="18" height="18" fill="#ffffff" />
                <rect x="163" y="150" width="34" height="40" fill="#ffffff" />
                <rect x="90" y="130" width="14" height="60" rx="7" fill="#166FE5" />
                <circle cx="97" cy="120" r="18" fill="#9CC5FB" />
                <rect x="252" y="140" width="14" height="50" rx="7" fill="#166FE5" />
                <circle cx="259" cy="130" r="16" fill="#9CC5FB" />
            </svg>
        </div>
    </x-slot:left>

    <div class="mx-auto w-full max-w-md rounded-[5px] border border-gray-100 bg-white p-6 shadow-xl shadow-gray-200/60 sm:p-8 lg:rounded-[10px]">
        <h2 class="text-xl font-bold text-gray-900">Create Your School Account</h2>
        <p class="mt-1 text-sm text-gray-600">Choose your preferred method to get started</p>

        <div class="mt-6 space-y-3">
            @foreach ([
                ['label' => 'Continue with Google', 'icon' => 'google'],
                ['label' => 'Continue with Microsoft', 'icon' => 'microsoft'],
                ['label' => 'Continue with Apple', 'icon' => 'apple'],
            ] as $provider)
                <button
                    type="button"
                    disabled
                    title="Coming soon"
                    class="flex w-full cursor-not-allowed items-center justify-center gap-3 rounded-[5px] border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-400 lg:rounded-[10px]"
                >
                    @if ($provider['icon'] === 'google')
                        <svg class="h-4 w-4 opacity-50" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 01-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.616z" />
                            <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 009 18z" />
                            <path fill="#FBBC05" d="M3.964 10.706A5.41 5.41 0 013.682 9c0-.593.102-1.17.282-1.706V4.962H.957A8.996 8.996 0 000 9c0 1.452.348 2.827.957 4.038l3.007-2.332z" />
                            <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 00.957 4.962L3.964 7.294C4.672 5.167 6.656 3.58 9 3.58z" />
                        </svg>
                    @elseif ($provider['icon'] === 'microsoft')
                        <svg class="h-4 w-4 opacity-50" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                            <rect x="1" y="1" width="9" height="9" fill="#F25022" />
                            <rect x="11" y="1" width="9" height="9" fill="#7FBA00" />
                            <rect x="1" y="11" width="9" height="9" fill="#00A4EF" />
                            <rect x="11" y="11" width="9" height="9" fill="#FFB900" />
                        </svg>
                    @else
                        <svg class="h-4 w-4 opacity-50" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.256-1.766-2.264-4.502-2.264-7.084 0-4.174 2.7-6.395 5.353-6.395 1.36 0 2.49.9 3.34.9.81 0 2.08-.96 3.61-.96.6 0 2.79.05 4.22 2.11-.11.07-2.52 1.47-2.52 4.5 0 3.57 3.12 4.88 3.16 4.9z" />
                        </svg>
                    @endif
                    {{ $provider['label'] }}
                    <span class="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">Soon</span>
                </button>
            @endforeach
        </div>

        <div class="my-6 flex items-center gap-3">
            <span class="h-px flex-1 bg-gray-200"></span>
            <span class="text-xs font-medium uppercase text-gray-400">or</span>
            <span class="h-px flex-1 bg-gray-200"></span>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="school_name" value="School Name" />
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4" y="7" width="16" height="14" rx="1" stroke="currentColor" stroke-width="1.5" />
                            <path d="M9 3h6v4H9z" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </span>
                    <x-text-input
                        id="school_name"
                        name="school_name"
                        type="text"
                        class="block w-full pl-10"
                        :value="old('school_name')"
                        required
                        autofocus
                        autocomplete="organization"
                        placeholder="Enter your school name"
                    />
                </div>
                <x-input-error :messages="$errors->get('school_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email Address" />
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5" />
                            <path d="M3 6l9 7 9-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <x-text-input
                        id="email"
                        name="email"
                        type="email"
                        class="block w-full pl-10"
                        :value="old('email')"
                        required
                        autocomplete="username"
                        placeholder="Enter your email address"
                    />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div x-data="{ show: false }">
                <x-input-label for="password" value="Password" />
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="10" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </span>
                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        x-bind:type="show ? 'text' : 'password'"
                        class="block w-full pl-10 pr-10"
                        required
                        autocomplete="new-password"
                        placeholder="Create a strong password"
                    />
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path x-show="!show" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <circle x-show="!show" cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                            <path x-show="show" d="M3 3l18 18M10.6 10.6a2.5 2.5 0 003.5 3.5M6.5 6.7C4 8.3 2 12 2 12s3.5 6 10 6c1.6 0 3-.35 4.2-.9M17.3 17.3C19.7 15.7 22 12 22 12s-1.3-2.3-3.5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div x-data="{ show: false }">
                <x-input-label for="password_confirmation" value="Confirm Password" />
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="10" width="14" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5" />
                            <path d="M8 10V7a4 4 0 018 0v3" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </span>
                    <x-text-input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        x-bind:type="show ? 'text' : 'password'"
                        class="block w-full pl-10 pr-10"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm your password"
                    />
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path x-show="!show" d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                            <circle x-show="!show" cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                            <path x-show="show" d="M3 3l18 18M10.6 10.6a2.5 2.5 0 003.5 3.5M6.5 6.7C4 8.3 2 12 2 12s3.5 6 10 6c1.6 0 3-.35 4.2-.9M17.3 17.3C19.7 15.7 22 12 22 12s-1.3-2.3-3.5-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div>
                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input
                        type="checkbox"
                        name="terms"
                        required
                        class="mt-0.5 rounded border-gray-300 text-primary-500 shadow-sm focus:ring-primary-500"
                    />
                    <span>
                        I agree to the
                        <a href="#" class="font-medium text-primary-500 hover:text-primary-600">Terms of Service</a>
                        and
                        <a href="#" class="font-medium text-primary-500 hover:text-primary-600">Privacy Policy</a>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('terms')" class="mt-2" />
            </div>

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 lg:rounded-[10px]"
            >
                Create Account
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </form>

        <div class="mt-6 flex items-start gap-3 rounded-[5px] bg-gray-50 p-4 text-xs text-gray-500 lg:rounded-[10px]">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            </svg>
            <p>
                <span class="font-medium text-gray-600">Your data is protected with enterprise-grade security.</span>
                By creating an account, you agree to our
                <a href="#" class="text-primary-500 hover:text-primary-600">Terms of Service</a>
                and
                <a href="#" class="text-primary-500 hover:text-primary-600">Privacy Policy</a>.
            </p>
        </div>
    </div>
</x-auth-layout>
