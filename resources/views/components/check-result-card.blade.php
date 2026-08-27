{{-- "Check Result", on a profile page.

     The brief puts the entry point on the profile rather than only on the
     results list, and for a parent with three children that is the difference
     between "check Ada's result" and "find Ada among the results". The profile
     is where you already are when you have a particular child in mind.

     It is a signpost, not a second gate: the token is entered on the results
     page, against the term it belongs to, and the rule that refuses a wrong
     token lives on the server in UnlocksResultsWithToken. Putting a token box
     here would mean two places to enter one token and two chances to disagree
     about what it unlocks. --}}
@props([
    'resultsUrl',
    'name' => null,
    'locked' => 0,
    'unlocked' => 0,
])

<div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Check Result</h3>

            <p class="mt-1.5 max-w-lg text-[12.5px] leading-[1.7] text-gray-500 dark:text-gray-400">
                @if ($name)
                    Enter the exam token your school issued for {{ $name }} to open a result.
                @else
                    Enter the exam token your school issued for you to open a result.
                @endif
                Each term has its own token, and a result stays open once its token has been accepted.
            </p>

            @if ($locked > 0 || $unlocked > 0)
                <p class="mt-3 flex flex-wrap items-center gap-3 text-[12px] font-semibold">
                    @if ($unlocked > 0)
                        <span class="text-green-600 dark:text-green-400">
                            <i class="fa-solid fa-lock-open mr-1 text-[11px]"></i>
                            {{ $unlocked }} {{ Str::plural('result', $unlocked) }} open
                        </span>
                    @endif

                    @if ($locked > 0)
                        <span class="text-gray-500 dark:text-gray-400">
                            <i class="fa-solid fa-lock mr-1 text-[11px]"></i>
                            {{ $locked }} still {{ $locked === 1 ? 'needs its token' : 'need their tokens' }}
                        </span>
                    @endif
                </p>
            @endif
        </div>

        <a
            href="{{ $resultsUrl }}"
            class="flex h-[38px] shrink-0 items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-4 text-[13px] font-bold text-white transition hover:bg-primary-700"
        >
            <i class="fa-solid fa-unlock-keyhole text-[12px]"></i>
            Check Result
        </a>
    </div>
</div>
