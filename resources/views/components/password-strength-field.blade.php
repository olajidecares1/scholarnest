@props([
    'name' => 'password',
    'confirmName' => 'password_confirmation',
    'label' => 'New Password',
    'confirmLabel' => 'Confirm New Password',
    'submitLabel' => 'Reset Password',
])

{{-- A password field with live strength feedback, its confirmation, and the
     submit button that stays disabled until both are satisfied.

     Kept as one component rather than three because the button's enabled state
     depends on both fields and on the same rules the strength meter draws -
     splitting them would mean duplicating those rules in two places and
     letting them drift.

     The rules here mirror Laravel's Password::defaults() so the meter agrees
     with the server. The meter is a courtesy: the server validates
     independently and rejects a weak password whatever this says. --}}
<div
    x-data="{
        password: '',
        confirmation: '',
        show: false,
        showConfirmation: false,

        get rules() {
            return [
                { label: 'At least 8 characters', met: this.password.length >= 8 },
                { label: 'One uppercase letter', met: /[A-Z]/.test(this.password) },
                { label: 'One lowercase letter', met: /[a-z]/.test(this.password) },
                { label: 'One number', met: /[0-9]/.test(this.password) },
                { label: 'One special character', met: /[^A-Za-z0-9]/.test(this.password) },
            ];
        },

        get met() {
            return this.rules.filter(rule => rule.met).length;
        },

        get strength() {
            if (! this.password.length) {
                return { level: 0, label: '', tone: 'bg-gray-200', text: 'text-gray-400' };
            }

            // Length is worth a step of its own: five short rules can all pass
            // on an eight-character password that is still trivially guessed.
            const bonus = this.password.length >= 12 ? 1 : 0;
            const score = Math.min(this.met + bonus, 5);

            return [
                { level: 1, label: 'Too weak', tone: 'bg-red-500', text: 'text-red-600' },
                { level: 1, label: 'Too weak', tone: 'bg-red-500', text: 'text-red-600' },
                { level: 2, label: 'Weak', tone: 'bg-orange-500', text: 'text-orange-600' },
                { level: 3, label: 'Medium', tone: 'bg-amber-500', text: 'text-amber-600' },
                { level: 4, label: 'Strong', tone: 'bg-lime-500', text: 'text-lime-600' },
                { level: 5, label: 'Very strong', tone: 'bg-green-600', text: 'text-green-600' },
            ][score];
        },

        get matches() {
            return this.confirmation.length > 0 && this.password === this.confirmation;
        },

        get ready() {
            return this.met === 5 && this.matches;
        },
    }"
    class="space-y-4"
>
    <div>
        <label for="{{ $name }}" class="field-label mb-1">{{ $label }}</label>

        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                <i class="fa-solid fa-lock text-[12px]"></i>
            </span>

            <input
                id="{{ $name }}"
                name="{{ $name }}"
                x-model="password"
                :type="show ? 'text' : 'password'"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Create a strong password"
                class="pl-9 pr-9"
            >

            <button
                type="button"
                @click="show = ! show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#9AAAC4] transition hover:text-[#5B7099]"
            >
                <i class="fa-solid text-[12px]" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        </div>

        <x-input-error :messages="$errors->get($name)" class="mt-1.5" />

        {{-- Strength meter. Five segments so the level is readable at a glance
             without having to parse the wording underneath. --}}
        <div x-show="password.length > 0" x-cloak class="mt-3">
            <div class="flex items-center gap-1.5">
                <template x-for="segment in 5" :key="segment">
                    <span
                        class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                        :class="segment <= strength.level ? strength.tone : 'bg-gray-200'"
                    ></span>
                </template>
            </div>

            <p class="mt-1.5 text-xs font-semibold" :class="strength.text" x-text="strength.label"></p>
        </div>

        {{-- Requirements, ticking over as they are met. --}}
        <ul x-show="password.length > 0" x-cloak class="mt-3 space-y-1">
            <template x-for="rule in rules" :key="rule.label">
                <li class="flex items-center gap-2 text-xs transition-colors duration-200" :class="rule.met ? 'text-green-600' : 'text-gray-500'">
                    <i class="fa-solid w-3.5 text-[11px]" :class="rule.met ? 'fa-circle-check' : 'fa-circle'"></i>
                    <span x-text="rule.label"></span>
                </li>
            </template>
        </ul>
    </div>

    <div>
        <label for="{{ $confirmName }}" class="field-label mb-1">{{ $confirmLabel }}</label>

        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[#9AAAC4]">
                <i class="fa-solid fa-lock text-[12px]"></i>
            </span>

            <input
                id="{{ $confirmName }}"
                name="{{ $confirmName }}"
                x-model="confirmation"
                :type="showConfirmation ? 'text' : 'password'"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Re-enter your password"
                class="pl-9 pr-9"
                :class="confirmation.length > 0 && ! matches ? 'field-invalid' : ''"
            >

            <button
                type="button"
                @click="showConfirmation = ! showConfirmation"
                :aria-label="showConfirmation ? 'Hide password' : 'Show password'"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-[#9AAAC4] transition hover:text-[#5B7099]"
            >
                <i class="fa-solid text-[12px]" :class="showConfirmation ? 'fa-eye-slash' : 'fa-eye'"></i>
            </button>
        </div>

        <p x-show="confirmation.length > 0 && ! matches" x-cloak class="mt-1.5 flex items-center gap-1.5 text-xs text-red-600">
            <i class="fa-solid fa-triangle-exclamation"></i> Both passwords must match.
        </p>

        <p x-show="matches" x-cloak class="mt-1.5 flex items-center gap-1.5 text-xs text-green-600">
            <i class="fa-solid fa-circle-check"></i> Passwords match.
        </p>
    </div>

    {{-- Enabled only once every rule passes and both fields agree. No
         "submitting" state is set here on purpose: disabling a submit button
         from inside its own click handler cancels the submission in most
         browsers, which is how a button ends up spinning forever. --}}
    <button
        type="submit"
        :disabled="! ready"
        class="btn flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-4 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
    >
        <i class="fa-solid fa-shield-halved"></i> {{ $submitLabel }}
    </button>
</div>
