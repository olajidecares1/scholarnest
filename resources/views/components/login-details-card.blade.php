{{-- The School Admin issuing someone their login details.

     Two fields, as asked: the username they sign in with, and the password.
     Which column the username is depends on the account, Staff ID for a
     teacher, Admission Number for a student, phone number for a parent, so
     the caller names it, and the same card serves all three.

     The password is never shown back. It is typed here, hashed, and handed on
     by the School Admin; a card that could redisplay it later would be a card
     backed by a system that had kept it in readable form. If it is lost, it is
     reset, not recovered. --}}
@props([
    'action',
    'username' => null,
    'usernameLabel' => 'Username',
    'usernameHint' => null,
    'accountName' => 'this account',
    'hasSignedIn' => false,
    'lastSignIn' => null,

    // wa.me link for this person. Always present, so the icon is never a
    // decoration that does nothing.
    'shareUrl' => null,
    'sharePhone' => null,
])

<div
    {{ $attributes->merge(['class' => 'rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]']) }}
    x-data="{
        password: '',
        confirmation: '',
        show: false,
        generate() {
            // Readable aloud and over the phone, which is how a School Admin
            // actually passes one on. No 0/O or 1/l to be misheard, and the
            // shape is fixed so it always satisfies the strength rules.
            const letters = 'ABCDEFGHJKMNPQRSTUVWXYZ'
            const lower = 'abcdefghijkmnpqrstuvwxyz'
            const digits = '23456789'
            const pick = (set, count) => Array.from(
                { length: count },
                () => set[Math.floor(Math.random() * set.length)],
            ).join('')

            this.password = pick(letters, 2) + pick(lower, 5) + pick(digits, 3) + '!'
            this.confirmation = this.password
            this.show = true
        },
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                <i class="fa-solid fa-key text-primary-500"></i>
                Login Details
            </h2>
            <small class="field-hint mt-0.5">
                Create the {{ $usernameLabel }} and password {{ $accountName }} will sign in with.
            </small>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($shareUrl)
                <a
                    href="{{ $shareUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn flex h-[34px] shrink-0 items-center gap-2 rounded-[8px] bg-[#25D366] px-3.5 text-[12.5px] font-bold text-white shadow-sm transition hover:bg-[#1FB855]"
                    title="{{ $sharePhone ? 'Open WhatsApp with this person' : 'Choose the recipient in WhatsApp' }}"
                >
                    {{-- The WhatsApp mark itself, drawn inline rather than
                         taken from an icon font. A brand glyph that fails to
                         load leaves a blank box where the button should be,
                         and the button then looks broken even though it
                         works. --}}
                    <i class="fa-brands fa-whatsapp text-[13px] leading-none" aria-hidden="true"></i>
                    Share via WhatsApp
                </a>
            @endif

            @if ($hasSignedIn)
            <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                Has signed in{{ $lastSignIn ? ' · '.$lastSignIn->diffForHumans() : '' }}
            </span>
        @else
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                Never signed in
            </span>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $action }}" class="mt-4 space-y-2">
        @csrf
        @method('PUT')

        <div>
            <label class="field-label">{{ $usernameLabel }}</label>

            {{-- The ID is the system's, not the School Admin's. Shown here
                 because it is half of what has to be passed on, and because a
                 password reset screen that did not say which account it was
                 for would be a poor one. --}}
            <p class="mt-1 flex h-[36px] items-center justify-between gap-2 rounded-[8px] border border-dashed border-[#CBD6E6] bg-[#F7FAFF] px-3 dark:border-gray-600 dark:bg-gray-900/40">
                <span class="truncate font-mono text-[12.5px] font-semibold text-[#0F2A5C] dark:text-gray-200">{{ $username ?: 'Not yet generated' }}</span>
                <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-[#8194B3]">Auto</span>
            </p>
            <small class="field-hint mt-1">{{ $usernameHint ?? 'Generated automatically and cannot be edited.' }}</small>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label for="credentials_password" class="field-label">Password</label>
                <button type="button" @click="generate()" class="text-[11px] font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Generate one
                </button>
            </div>

            <div class="relative mt-1">
                <input
                    id="credentials_password"
                    :type="show ? 'text' : 'password'"
                    type="password"
                    name="password"
                    x-model="password"
                    required
                    autocomplete="new-password"
                    class="pr-9 {{ $errors->has('password') ? 'field-invalid' : '' }}"
                >
                <button
                    type="button"
                    @click="show = ! show"
                    tabindex="-1"
                    :aria-label="show ? 'Hide password' : 'Show password'"
                    class="absolute right-0 top-0 flex w-9 items-center justify-center text-[#9AAAC4] transition hover:text-[#5B7099]"
                    style="height: var(--field-height);"
                >
                    <i class="fa-solid text-[12px]" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>

            @error('password')
                <p class="mt-1 text-[11px] font-medium leading-[1.45] text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="credentials_password_confirmation" class="field-label">Confirm Password</label>
            <input
                id="credentials_password_confirmation"
                :type="show ? 'text' : 'password'"
                type="password"
                name="password_confirmation"
                x-model="confirmation"
                required
                autocomplete="new-password"
                class="mt-1"
            >
        </div>

        {{-- Said here because this is the moment the School Admin has the
             password in front of them, and the only moment they will. --}}
        <div class="flex items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-900/20">
            <i class="fa-solid fa-circle-info mt-0.5 text-[11px] text-amber-600 dark:text-amber-400"></i>
            <p class="text-[11px] leading-[1.5] text-amber-800 dark:text-amber-300">
                Write this password down before saving &mdash; it is stored encrypted and cannot be shown again.
                If it is lost, set a new one here.
            </p>
        </div>

        <button
            type="submit"
            class="btn mt-1 h-[38px] w-full rounded-[8px] bg-primary-500 text-[13px] font-bold text-white transition hover:bg-primary-600"
        >
            Save Login Details
        </button>
    </form>
</div>
