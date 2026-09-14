{{-- The exam-token gate on a portal result.

     Three states, and the component draws whichever applies:

       withheld, the school is holding the result over unpaid fees. No token
                   will open it, so no token is asked for.
       locked, the token has not been entered in this session. The button
                   opens a modal asking for it.
       unlocked, already redeemed. The caller renders its own View/Download
                   controls in the slot.

     Shared by the student and guardian portals so the two cannot drift into
     asking for the same thing in two different ways. --}}
@props([
    'examination',
    'student',
    'unlockUrl',
    'withheld' => false,
    'withheldMessage' => null,
    'unlocked' => false,
])

@php
    // Unique per result, so several gates can sit on one page without their
    // modals or their error messages reaching into each other.
    $gateId = 'result-gate-'.$examination->id;
    $errorBag = $errors->getBag('default');
    $failedHere = session('token_gate_examination') === $examination->id;
@endphp

<div>
    @if ($withheld)
        <span
            class="flex shrink-0 items-center gap-1.5 rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400"
            title="Withheld until the outstanding school-fee balance is settled"
        >
            <i class="fa-solid fa-lock text-[10px]"></i>
            Locked
        </span>
    @elseif ($unlocked)
        {{ $slot }}
    @else
        <div x-data="{ open: {{ $failedHere ? 'true' : 'false' }} }">
            <button
                type="button"
                @click="open = true; $nextTick(() => $refs.token?.focus())"
                class="flex shrink-0 items-center gap-1.5 rounded-[8px] border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/20 dark:text-primary-300"
            >
                <i class="fa-solid fa-key text-[10px]"></i>
                Enter Exam Token
            </button>

            <div
                x-show="open"
                x-cloak
                @keydown.escape.window="open = false"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="{{ $gateId }}-title"
            >
                <div class="absolute inset-0 bg-slate-900/50" @click="open = false" x-transition.opacity></div>

                <div
                    class="relative w-full max-w-sm overflow-hidden rounded-[14px] bg-white shadow-[0_24px_60px_-24px_rgba(15,42,92,0.55)] dark:bg-gray-800"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                >
                    <div class="border-b border-gray-100 px-5 py-4 text-center dark:border-gray-700">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-500 dark:bg-primary-900/30">
                            <i class="fa-solid fa-key text-lg"></i>
                        </span>
                        <h2 id="{{ $gateId }}-title" class="mt-3 text-sm font-bold text-gray-900 dark:text-white">Enter Exam Token</h2>
                        <p class="field-hint mt-1">
                            {{ $examination->name }} &middot; {{ $examination->term->label() }}, {{ $examination->session }}
                        </p>
                    </div>

                    <form method="POST" action="{{ $unlockUrl }}" class="px-5 py-4" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf

                        <label for="{{ $gateId }}-token" class="field-label">Exam Token</label>
                        <input
                            id="{{ $gateId }}-token"
                            x-ref="token"
                            type="text"
                            name="token"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="20"
                            placeholder="15-character exam token"
                            class="mt-1 text-center uppercase tracking-[0.12em] {{ $failedHere && $errorBag->has('token') ? 'field-invalid' : '' }}"
                        >

                        @if ($failedHere && $errorBag->has('token'))
                            <p class="mt-1 flex items-start gap-1 text-[11px] font-medium leading-[1.45] text-red-600 dark:text-red-400">
                                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0 text-[10px]"></i>
                                {{ $errorBag->first('token') }}
                            </p>
                        @endif

                        <p class="field-hint mt-2">
                            This is the token your school issued for
                            <span class="font-semibold">{{ $student->fullName() }}</span>&rsquo;s
                            {{ $examination->term->label() }} result. A token for another student or another term will not work.
                        </p>

                        <div class="mt-4 flex gap-2">
                            <button
                                type="button"
                                @click="open = false"
                                class="h-[38px] flex-1 rounded-[8px] border border-gray-300 text-[13px] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Cancel
                            </button>

                            {{-- Disabled on the form's submit event rather than
                                 the button's click: disabling a submit button
                                 inside its own click handler cancels the
                                 submission in most browsers. --}}
                            <button
                                type="submit"
                                :disabled="submitting"
                                class="h-[38px] flex-1 rounded-[8px] bg-primary-500 text-[13px] font-bold text-white transition hover:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                <span x-show="! submitting">Check Result</span>
                                <span x-show="submitting" x-cloak>Checking&hellip;</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
