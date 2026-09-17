{{-- One school's student/pupil capacity, told the same way everywhere.

     Every figure here comes from StudentLicenceAllocation, which is the same
     service the server consults before it will create a student, so what this
     card says and what the system actually enforces cannot drift apart. There
     is no arithmetic in this file and nothing is counted in the browser.

     Pending requests are shown but never added in: a submitted payment is a
     request, and a request buys nothing until a Super Admin approves it. --}}
@props([
    // array{initial: int, allocated: int, used: int, remaining: int, pending: int}
    'capacity',

    // Whether to offer the "Add More Students/Pupils" button. Off on the page
    // that button already leads to.
    'action' => true,
])

{{-- Nothing to draw when the plan records no capacity, which is a school on a
     per-student plan whose student count was never set. Every page that shows
     this card guards against it, and this is the second line: a missing figure
     should leave a card out, never throw the page away. --}}
@if ($capacity === null)
    @php return; @endphp
@endif

@php
    $exhausted = $capacity['remaining'] === 0;
    $runningLow = ! $exhausted && ($capacity['runningLow'] ?? false);
    $usedPercent = $capacity['allocated'] > 0
        ? min(100, (int) round($capacity['used'] / $capacity['allocated'] * 100))
        : 0;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                <i class="fa-solid fa-user-group text-primary-500"></i>
                Student/Pupil Capacity
            </h2>
            <p class="field-hint mt-0.5">
                Your Basic plan is billed per student, per term. This is one cumulative capacity, not separate batches.
            </p>
        </div>

        @if ($action && \Illuminate\Support\Facades\Route::has('subscription-top-up.create'))
            <a
                href="{{ route('subscription-top-up.create') }}"
                @class([
                    'shrink-0 rounded-[8px] px-4 py-2 text-sm font-semibold text-white shadow-sm transition',
                    'bg-red-600 hover:bg-red-700' => $exhausted,
                    'bg-amber-600 hover:bg-amber-700' => $runningLow,
                    'bg-primary-600 hover:bg-primary-700' => ! $exhausted && ! $runningLow,
                ])
            >
                <i class="fa-solid fa-plus mr-1.5 text-xs"></i>
                Add More Students/Pupils
            </a>
        @endif
    </div>

    <dl class="mt-4 grid grid-cols-3 gap-3">
        <div class="rounded-[5px] bg-gray-50 p-3 dark:bg-gray-900/40 lg:rounded-[8px]">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Approved</dt>
            <dd class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($capacity['allocated']) }}</dd>
        </div>
        <div class="rounded-[5px] bg-gray-50 p-3 dark:bg-gray-900/40 lg:rounded-[8px]">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Registered</dt>
            <dd class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($capacity['used']) }}</dd>
        </div>
        <div class="rounded-[5px] bg-gray-50 p-3 dark:bg-gray-900/40 lg:rounded-[8px]">
            <dt @class([
                'text-xs font-medium',
                'text-red-600 dark:text-red-400' => $exhausted,
                'text-gray-500 dark:text-gray-400' => ! $exhausted,
            ])>Available</dt>
            <dd @class([
                'mt-1 text-2xl font-extrabold',
                'text-red-600 dark:text-red-400' => $exhausted,
                'text-gray-900 dark:text-white' => ! $exhausted,
            ])>{{ number_format($capacity['remaining']) }}</dd>
        </div>
    </dl>

    <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
        <div
            @class([
                'h-full rounded-full',
                'bg-red-500' => $exhausted,
                'bg-amber-500' => $runningLow,
                'bg-primary-600' => ! $exhausted && ! $runningLow,
            ])
            style="width: {{ $usedPercent }}%"
        ></div>
    </div>

    @if ($exhausted)
        <div class="mt-4 flex items-start gap-2.5 rounded-[5px] border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20 lg:rounded-[10px]">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-600 dark:text-red-400"></i>
            <div>
                <p class="text-sm font-bold text-red-800 dark:text-red-300">Student/Pupil Capacity Reached</p>
                <p class="mt-0.5 text-sm text-red-700 dark:text-red-400">
                    You have used all {{ number_format($capacity['allocated']) }} approved student/pupil spaces.
                    Request additional spaces to register more students/pupils.
                </p>
            </div>
        </div>
    @endif

    @if ($runningLow)
        {{-- Worth saying before the wall rather than at it: additional spaces
             need a payment and an approval, which takes longer than the moment
             a school discovers it cannot admit the next student. --}}
        <div class="mt-4 flex items-start gap-2.5 rounded-[5px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20 lg:rounded-[10px]">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600 dark:text-amber-400"></i>
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Running low on student/pupil spaces</p>
                <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-400">
                    You have {{ number_format($capacity['remaining']) }} of {{ number_format($capacity['allocated']) }}
                    approved student/pupil {{ \Illuminate\Support\Str::plural('space', $capacity['allocated']) }} left.
                    Additional spaces need a payment and AkademicNest Team approval, so it is worth starting before you run out.
                </p>
            </div>
        </div>
    @endif

    @if ($capacity['pending'] > 0)
        {{-- Said plainly, because the alternative is a school assuming its
             payment has already raised the limit and finding out at the point
             of registering a student that it has not. --}}
        <div class="mt-3 flex items-start gap-2.5 rounded-[5px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20 lg:rounded-[10px]">
            <i class="fa-solid fa-clock mt-0.5 text-amber-600 dark:text-amber-400"></i>
            <p class="text-sm text-amber-800 dark:text-amber-300">
                <span class="font-semibold">{{ number_format($capacity['pending']) }} additional student/pupil
                {{ \Illuminate\Support\Str::plural('space', $capacity['pending']) }} awaiting AkademicNest Team approval.</span>
                These spaces cannot be used until the payment is verified and approved &mdash; your capacity is still
                {{ number_format($capacity['allocated']) }}.
            </p>
        </div>
    @endif
</div>
