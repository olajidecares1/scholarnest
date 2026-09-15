{{-- "Forgot password?" in three steps on one page: email, 6-digit code, new
     password. Which step shows is decided on the server
     (PasswordResetController::show), so the password fields are never in the
     page until the code has been verified, whatever the browser does. --}}
@php
    $steps = [
        'email' => ['Email', 'fa-envelope'],
        'code' => ['Code', 'fa-shield-halved'],
        'password' => ['New password', 'fa-key'],
    ];
    $order = array_keys($steps);
    $position = $step === 'done' ? 3 : array_search($step, $order, true);

    $header = match ($step) {
        'code' => ['fa-envelope-open-text', 'Check Your Email', "Enter the 6-digit verification code we sent to your email address. It expires in {$expiresMinutes} minutes."],
        'password' => ['fa-key', 'Choose a New Password', 'Your code has been verified. Enter and confirm your new password.'],
        'done' => ['fa-circle-check', 'Password Reset', 'Your password has been changed. You can now sign in with your new password.'],
        default => ['fa-lock', 'Forgot Your Password?', 'Enter the email address for your AkademicNest account and we will email you a 6-digit verification code.'],
    };

    $button = 'flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';
@endphp

<x-auth-layout :title="'Forgot Password | '.config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span @class([
                'flex h-14 w-14 items-center justify-center rounded-[10px] text-2xl',
                'bg-green-100 text-green-600' => $step === 'done',
                'bg-primary-100 text-primary-600' => $step !== 'done',
            ])>
                <i class="fa-solid {{ $header[0] }}" aria-hidden="true"></i>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">{{ $header[1] }}</h2>
        <p class="mt-1 text-center text-sm text-gray-600">{{ $header[2] }}</p>

        {{-- Where they are in the three steps. --}}
        <ol class="mt-5 grid grid-cols-3 gap-2" aria-label="Password reset progress">
            @foreach ($steps as $key => [$label, $icon])
                @php $index = array_search($key, $order, true); @endphp
                <li @class([
                    'flex flex-col items-center gap-1 rounded-[8px] border px-2 py-2 text-center text-[11px] font-semibold',
                    'border-green-200 bg-green-50 text-green-700' => $index < $position,
                    'border-primary-300 bg-primary-50 text-primary-700' => $index === $position,
                    'border-gray-200 text-gray-400' => $index > $position,
                ]) @if ($index === $position) aria-current="step" @endif>
                    <i class="fa-solid {{ $index < $position ? 'fa-check' : $icon }}" aria-hidden="true"></i>
                    {{ $label }}
                </li>
            @endforeach
        </ol>

        @if (session('status') && $step !== 'done')
            <div class="mt-4 flex items-start gap-2 rounded-[8px] border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status">
                <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($step === 'email')
            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-3" x-data="{ sending: false }" @submit="sending = true">
                @csrf

                <x-auth-email-input :value="old('email')" autofocus helper="The email address you use to sign in." />

                <button type="submit" class="{{ $button }}" :disabled="sending">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span x-show="! sending">Send Verification Code</span>
                    <span x-show="sending" x-cloak>Sending&hellip;</span>
                </button>
            </form>
        @elseif ($step === 'code')
            <p class="mt-4 flex items-center justify-center gap-2 text-sm text-gray-700">
                <i class="fa-solid fa-at text-gray-400" aria-hidden="true"></i>
                <span class="font-semibold">{{ $email }}</span>
            </p>

            <form method="POST" action="{{ route('password.verify') }}" class="mt-4 space-y-3" x-data="{ sending: false }" @submit="sending = true">
                @csrf

                {{-- inputmode="numeric" so a phone offers the number pad, and
                     autocomplete="one-time-code" so it can be filled from the
                     email notification rather than retyped. --}}
                <div>
                    <label for="code" class="field-label">
                        <i class="fa-solid fa-shield-halved mr-1 text-primary-500" aria-hidden="true"></i>
                        6-Digit Verification Code
                    </label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        autofocus
                        placeholder="000000"
                        class="mt-1 w-full text-center text-lg font-bold tracking-[0.5em] @error('code') field-invalid @enderror"
                    >
                    @error('code')
                        <p class="mt-1 flex items-start gap-1.5 text-xs font-medium text-red-600" role="alert">
                            <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
                            <span>{{ $message }}</span>
                        </p>
                    @else
                        <p class="field-hint mt-1">Copy the code from the email we sent you. Check your spam folder if you cannot see it.</p>
                    @enderror
                </div>

                <button type="submit" class="{{ $button }}" :disabled="sending">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span x-show="! sending">Verify Code</span>
                    <span x-show="sending" x-cloak>Checking&hellip;</span>
                </button>
            </form>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" class="inline-flex items-center gap-1.5 font-semibold text-primary-500 hover:text-primary-600">
                        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Send a new code
                    </button>
                </form>

                <form method="POST" action="{{ route('password.restart') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 font-semibold text-gray-500 hover:text-gray-700">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i> Use a different email
                    </button>
                </form>
            </div>
        @elseif ($step === 'password')
            <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-3" x-data="{ sending: false }" @submit="sending = true">
                @csrf

                <x-auth-password-input
                    id="password"
                    name="password"
                    label="New Password"
                    placeholder="Create a strong password"
                    helper="At least 8 characters, with upper and lower case letters, a number and a symbol."
                    autocomplete="new-password"
                />

                <x-auth-password-input
                    id="password_confirmation"
                    name="password_confirmation"
                    label="Confirm New Password"
                    placeholder="Enter the new password again"
                    autocomplete="new-password"
                />

                <button type="submit" class="{{ $button }}" :disabled="sending">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span x-show="! sending">Reset Password</span>
                    <span x-show="sending" x-cloak>Saving&hellip;</span>
                </button>
            </form>
        @else
            <a href="{{ $signInUrl }}" class="{{ $button }} mt-6">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                Go to Sign In
            </a>
        @endif

        @if ($step === 'email')
            <p class="mt-6 text-center text-sm text-gray-600">
                <a href="{{ route('portal.show') }}" class="inline-flex items-center gap-1.5 font-semibold text-primary-500 hover:text-primary-600">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Sign In
                </a>
            </p>
        @endif
    </x-auth-card>
</x-auth-layout>
