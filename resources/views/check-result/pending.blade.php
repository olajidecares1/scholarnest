{{-- The token was valid. The school has not published the result yet.

     A third thing the page has to be able to say, alongside "wrong token" and
     "on hold over fees". Showing the marks as they stand would hand over a
     half-entered card and call it a result; showing an error would tell a
     parent their token had failed when it had not.

     Reachable only by somebody who has already proved a claim to this
     particular child's result, so naming the child and the term leaks
     nothing. --}}
<x-auth-layout :title="'Result not published yet | '.$school->name" :school="$school" simple>
    <x-auth-card>
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-500">
                <i class="fa-regular fa-clock text-xl"></i>
            </span>

            <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.14em] text-blue-600">Not yet released</p>
            <h2 class="mt-1.5 text-xl font-bold tracking-tight text-gray-900">This result is not ready</h2>
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

        <p class="mt-4 text-[12.5px] leading-[1.7] text-gray-600">
            {{ $school->name }} has not released this result yet. Results appear here once the school has
            finished entering the marks and published them.
        </p>

        {{-- The same reassurance the fee-hold page gives, and for the same
             reason: the natural reading of any refusal is that the token has
             been used up. It has not. --}}
        <div class="mt-4 flex items-start gap-2.5 rounded-[8px] border border-blue-100 bg-blue-50/70 p-4">
            <i class="fa-solid fa-circle-info mt-0.5 text-[12px] text-blue-500"></i>
            <p class="text-[12px] leading-[1.6] text-blue-900">
                Your token is still valid. Use it again once the school has published the result &mdash;
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
