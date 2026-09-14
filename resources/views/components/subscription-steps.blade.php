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
    class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm sm:p-6 lg:rounded-[10px]"
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
        <p class="text-[11px] font-bold uppercase tracking-wide text-primary-600">
            Step {{ $position }} of {{ $total }}
        </p>
        <p class="truncate text-[11px] font-semibold text-gray-500">{{ $steps->values()[$position - 1] ?? '' }}</p>
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
                        "after:mx-2 after:mt-4 after:h-0.5 after:w-6 after:shrink-0 after:content-[''] sm:after:mx-3 sm:after:w-auto sm:after:min-w-4 sm:after:flex-1" => $number !== $total,
                        'after:bg-green-500' => $number < $position,
                        'after:bg-gray-200' => $number >= $position,
                    ])
                >
                    <div class="flex w-full min-w-0 flex-col items-center px-1 text-center sm:px-0">
                        <span
                            @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors sm:h-10 sm:w-10',
                                'bg-green-500 text-white' => $number < $position,
                                'bg-primary-600 text-white ring-4 ring-primary-100' => $number === $position,
                                'bg-gray-100 text-gray-400' => $number > $position,
                            ])
                        >
                            @if ($number < $position)
                                <i class="fa-solid fa-check text-xs"></i>
                            @else
                                {{ $number }}
                            @endif
                        </span>

                        <div class="mt-2 w-full min-w-0">
                            {{-- Wraps below "sm", where there is room for two
                                 lines and none to spare sideways; truncates
                                 from "sm" up, where the columns are even. --}}
                            <p @class([
                                'text-[11px] font-bold leading-[1.3] sm:truncate sm:text-xs',
                                'text-gray-900' => $number <= $position,
                                'text-gray-400' => $number > $position,
                            ])>
                                {{ $label }}
                            </p>
                            <p class="mt-0.5 text-[10px] font-semibold leading-tight text-gray-600">
                                @if ($number < $position)
                                    Completed
                                @elseif ($number === $position)
                                    In Progress
                                @else
                                    Pending
                                @endif
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
