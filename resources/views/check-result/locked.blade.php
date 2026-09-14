{{-- The token was valid. The result is being withheld.

     Two different things, and the page has to say which. A parent who typed a
     correct token and got the same vague "check your token" message they would
     have got for a wrong one would retype it, then telephone the school to ask
     what was wrong with a token that was never wrong.

     So this page names the balance and says what happens next. It is only
     reachable by someone who has already proved a claim to this particular
     student's result, so there is nothing here to leak. --}}
<x-auth-layout :title="'Result on hold | '.$school->name" :school="$school" simple>
    <x-auth-card>
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-500">
                <i class="fa-solid fa-lock text-xl"></i>
            </span>

            <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.14em] text-amber-600">Outstanding school fees</p>
            <h2 class="mt-1.5 text-xl font-bold tracking-tight text-gray-900">This result is on hold</h2>
        </div>

        <dl class="mt-5 rounded-[8px] border border-gray-200 bg-gray-50 p-4 text-[12.5px]">
            <div class="flex justify-between gap-3 py-1">
                <dt class="text-gray-500">Student</dt>
                <dd class="text-right font-semibold text-gray-900">{{ $student->fullName() }}</dd>
            </div>
            <div class="flex justify-between gap-3 py-1">
                <dt class="text-gray-500">Examination</dt>
                <dd class="text-right font-semibold text-gray-900">{{ $examination->name }}</dd>
            </div>
            <div class="flex justify-between gap-3 py-1">
                <dt class="text-gray-500">Term</dt>
                <dd class="text-right font-semibold text-gray-900">{{ $examination->term->label() }}, {{ $examination->session }}</dd>
            </div>
        </dl>

        <p class="mt-4 text-[12.5px] leading-[1.7] text-gray-600">{{ $message }}</p>

        {{-- Said explicitly, because the natural assumption on seeing this is
             that the token has been spent or has stopped working. It has not. --}}
        <div class="mt-4 flex items-start gap-2.5 rounded-[8px] border border-blue-100 bg-blue-50/70 p-4">
            <i class="fa-solid fa-circle-info mt-0.5 text-[12px] text-blue-500"></i>
            <p class="text-[12px] leading-[1.6] text-blue-900">
                Your token is still valid. Use it again once the school has released the result &mdash;
                there is no need to ask for a new one.
            </p>
        </div>

        <a
            href="{{ $school->resultLinkUrl() }}"
            class="mt-6 flex h-[42px] w-full items-center justify-center gap-2 rounded-[8px] border border-gray-300 bg-white text-[13px] font-bold text-gray-700 transition hover:bg-gray-50"
        >
            <i class="fa-solid fa-arrow-left text-[12px]"></i>
            Back to result checker
        </a>
    </x-auth-card>
</x-auth-layout>
