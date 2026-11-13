@props([
    // Which step this page is, by name rather than by number. The positions
    // shift depending on the plan, so a hard-coded number on each page is a
    // standing invitation for the bar to disagree with itself.
    'step',

    // Whether this subscription is priced per student. Read from the wizard
    // session by default; the confirmation page passes it because the wizard
    // has been cleared by the time it renders, and the bar should not lose a
    // step the school actually walked through.
    'perStudent' => null,
])

@php
    // The quantity step exists only where the price is per student. Showing a
    // flat-fee plan a step it can never visit would misdescribe its process.
    $perStudent ??= app(App\Services\SubscriptionWizardService::class)->isPerStudent();

    // Labels only. Each step once carried a "description" that nothing
    // rendered, the bar shows Completed / In Progress / Pending underneath
    // instead, so those were data that looked meaningful while changing
    // nothing on screen.
    //
    // "Students & Amount" is its own step because for a Basic school that
    // number decides both the price and the capacity it is held to for the
    // term, and it sits before every payment detail.
    //
    // The last step is named for what happens there. It read "Confirmation",
    // which suggested the school was finished when in fact nothing is active
    // until a Super Admin has reviewed the payment.
    $steps = collect([
        'choose-plan' => 'Choose Plan',
        'students' => 'Students & Amount',
        'billing-details' => 'Billing Details',
        'payment-method' => 'Payment Method',
        'review' => 'Review & Confirm',
        'confirmation' => 'Awaiting Approval',
    ])->reject(fn (string $label, string $key) => $key === 'students' && ! $perStudent);

    $position = $steps->keys()->search($step) + 1;
    $total = $steps->count();
@endphp

{{-- Six steps do not fit across a phone, and the answer is not to shrink them
     until the labels collide, it is to let the bar be wider than the screen
     and slide.

     So each step keeps a real width below "sm" and the row scrolls; from "sm"
     up the steps go back to sharing the width evenly, because there it fits.
     The current step is scrolled into view on arrival, so a school landing on
     step 4 of 6 sees step 4 rather than having to drag to find out where it
     is. --}}
<div
    class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:px-6 sm:py-4 dark:border-gray-700 dark:bg-gray-800"
    x-data="{
        centreCurrentStep() {
            const rail = this.$refs.rail;
            const current = this.$refs.current;

            if (! rail || ! current || rail.scrollWidth <= rail.clientWidth) {
                return;
            }

            // Left-aligned rather than centred for step one, so the bar does
            // not open already scrolled away from its own beginning.
            rail.scrollLeft = Math.max(
                0,
                current.offsetLeft - (rail.clientWidth / 2) + (current.offsetWidth / 2),
            );
        },
    }"
    x-init="$nextTick(() => centreCurrentStep())"
>
    {{-- A plain "Step 3 of 6" above the rail. On a narrow screen part of the
         bar is always off-screen, so the one fact the school most needs,
         where they are, and how much is left, is stated in words that never
         scroll away. --}}
    <div class="mb-3 flex items-baseline justify-between gap-3 sm:hidden">
        <p class="text-[10.5px] font-semibold uppercase tracking-[0.08em] text-primary-600">
            Step {{ $position }} of {{ $total }}
        </p>
        <small class="block truncate text-[11px] font-medium text-slate-500">{{ $steps->values()[$position - 1] ?? '' }}</small>
    </div>

    <div
        x-ref="rail"
        class="subscription-steps-rail -mx-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:overflow-x-visible sm:px-0 sm:pb-0"
    >
        <ol class="flex w-max items-start sm:w-auto sm:justify-between">
            @foreach ($steps->values() as $index => $label)
                @php($number = $index + 1)

                <li
                    @if ($number === $position) x-ref="current" @endif
                    @class([
                        // Below "sm" every step keeps its own width and the row
                        // overflows; from "sm" they share the space instead.
                        'flex w-[108px] shrink-0 items-center sm:w-auto sm:min-w-0 sm:flex-1',
                        "after:mx-2 after:mt-[13px] after:h-[2px] after:w-6 after:shrink-0 after:rounded-full after:content-[''] sm:after:mx-2.5 sm:after:mt-[15px] sm:after:w-auto sm:after:min-w-4 sm:after:flex-1" => $number !== $total,
                        'after:bg-emerald-500' => $number < $position,
                        'after:bg-slate-200 dark:after:bg-gray-700' => $number >= $position,
                    ])
                >
                    <div class="flex w-full min-w-0 flex-col items-center px-1 text-center sm:px-0">
                        <span
                            @class([
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-semibold transition-colors sm:h-8 sm:w-8',
                                'bg-emerald-500 text-white' => $number < $position,
                                'bg-primary-600 text-white shadow-sm shadow-primary-600/30 ring-[3px] ring-primary-100 dark:ring-primary-900/60' => $number === $position,
                                'border border-slate-200 bg-white text-slate-400 dark:border-gray-600 dark:bg-gray-800' => $number > $position,
                            ])
                        >
                            @if ($number < $position)
                                <i class="fa-solid fa-check text-[11px]" aria-hidden="true"></i>
                            @else
                                {{ $number }}
                            @endif
                        </span>

                        <div class="mt-1.5 w-full min-w-0">
                            {{-- Wraps below "sm", where there is room for two
                                 lines and none to spare sideways; truncates
                                 from "sm" up, where the columns are even. --}}
                            <p @class([
                                'text-[11px] font-semibold leading-[1.3] sm:line-clamp-2 sm:text-[11.5px]',
                                'text-slate-900 dark:text-white' => $number <= $position,
                                'text-slate-400' => $number > $position,
                            ])>
                                {{ $label }}
                            </p>
                            <small class="mt-0.5 block text-[10px] font-medium leading-tight text-slate-400">
                                @if ($number < $position)
                                    Completed
                                @elseif ($number === $position)
                                    In Progress
                                @else
                                    Pending
                                @endif
                            </small>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
