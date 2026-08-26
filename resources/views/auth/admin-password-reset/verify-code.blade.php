@php
    $cooldown = \App\Http\Controllers\Auth\AdminPasswordResetController::RESEND_COOLDOWN_SECONDS;
@endphp

<x-auth-layout :title="'Check your email - ' . config('app.name')" simple>
    <x-auth-card>
        {{-- Six separate boxes rather than one input with letter-spacing.
             Typing moves forward, Backspace moves back, and a pasted code
             fills the row - the hidden real field underneath is what actually
             submits, so the server still receives a plain six-digit string. --}}
        <div
            x-data="{
                digits: ['', '', '', '', '', ''],
                seconds: {{ session('status') ? $cooldown : 0 }},
                timer: null,

                get code() {
                    return this.digits.join('');
                },

                get complete() {
                    return this.code.length === 6;
                },

                onInput(index, event) {
                    const value = event.target.value.replace(/\D/g, '');

                    // A paste lands entirely in one box; spread it across the
                    // row rather than dropping all but the first character.
                    if (value.length > 1) {
                        value.split('').slice(0, 6 - index).forEach((digit, offset) => {
                            this.digits[index + offset] = digit;
                        });

                        this.focusAt(Math.min(index + value.length, 5));

                        return;
                    }

                    this.digits[index] = value;

                    if (value && index < 5) {
                        this.focusAt(index + 1);
                    }
                },

                onKeydown(index, event) {
                    if (event.key === 'Backspace' && ! this.digits[index] && index > 0) {
                        this.focusAt(index - 1);
                    }
                },

                focusAt(index) {
                    this.$nextTick(() => this.$refs['digit' + index]?.focus());
                },

                startCountdown() {
                    clearInterval(this.timer);

                    this.timer = setInterval(() => {
                        if (this.seconds > 0) {
                            this.seconds--;
                        } else {
                            clearInterval(this.timer);
                        }
                    }, 1000);
                },
            }"
            x-init="if (seconds > 0) startCountdown()"
        >
            <div class="flex justify-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-500">
                    <i class="fa-solid fa-envelope-open-text text-xl"></i>
                </span>
            </div>

            <h1 class="mt-5 text-center text-2xl font-bold tracking-tight text-gray-900">Check your email</h1>
            <p class="mx-auto mt-2 max-w-xs text-center text-sm leading-relaxed text-gray-500">
                We&rsquo;ve sent a verification code to your registered email address.
            </p>

            @if (session('status'))
                <p class="mt-5 flex items-center justify-center gap-2 rounded-[8px] bg-green-50 px-3 py-2.5 text-xs font-medium text-green-700">
                    <i class="fa-solid fa-circle-check"></i> {{ session('status') }}
                </p>
            @endif

            <form method="POST" action="{{ route('admin.password-reset.verify-code', $token) }}" class="mt-7">
                @csrf

                <p class="text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Enter the 6-digit code</p>

                <div class="mt-3 flex justify-center gap-2" dir="ltr">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            x-ref="digit{{ $i }}"
                            :value="digits[{{ $i }}]"
                            @input="onInput({{ $i }}, $event)"
                            @keydown="onKeydown({{ $i }}, $event)"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                            @if ($i === 0) autofocus @endif
                            aria-label="Digit {{ $i + 1 }}"
                            class="w-11 text-center transition sm:w-12"
                            :class="digits[{{ $i }}] ? 'field-filled' : ''"
                        >
                    @endfor
                </div>

                {{-- What the server actually reads. The boxes are presentation;
                     this is the field. --}}
                <input type="hidden" name="code" :value="code">

                @error('code')
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-red-600">
                        <i class="fa-solid fa-triangle-exclamation"></i> {{ $message }}
                    </p>
                @enderror

                <button
                    type="submit"
                    :disabled="! complete"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                >
                    <i class="fa-solid fa-shield-check"></i> Verify Code
                </button>
            </form>

            <div class="mt-6 text-center text-xs text-gray-500">
                <span>Didn&rsquo;t receive the code?</span>

                {{-- The countdown mirrors the server's cooldown exactly, so the
                     link re-enables at the moment another request would be
                     accepted rather than a moment before. --}}
                <form
                    method="POST"
                    action="{{ route('admin.password-reset.resend', $token) }}"
                    class="mt-1.5"
                    @submit="seconds = {{ $cooldown }}; startCountdown()"
                >
                    @csrf

                    <button
                        type="submit"
                        x-show="seconds === 0"
                        class="font-semibold text-primary-500 transition hover:text-primary-600"
                    >
                        <i class="fa-solid fa-rotate-right"></i> Resend code
                    </button>

                    <span x-show="seconds > 0" x-cloak class="text-gray-400">
                        You can request another code in <span class="font-semibold tabular-nums" x-text="seconds"></span>s
                    </span>
                </form>
            </div>

            <p class="mt-6 border-t border-gray-100 pt-5 text-center text-xs text-gray-400">
                <a href="{{ route('admin.password-reset.request') }}" class="font-semibold text-gray-500 transition hover:text-gray-700">
                    <i class="fa-solid fa-arrow-left"></i> Start again with a new link
                </a>
            </p>
        </div>
    </x-auth-card>
</x-auth-layout>
