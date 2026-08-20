@props(['current'])

@php
    $steps = [
        1 => ['label' => 'Choose Plan', 'description' => 'Select a subscription plan'],
        2 => ['label' => 'Billing Details', 'description' => 'Confirm your school information'],
        3 => ['label' => 'Payment Method', 'description' => 'Choose how to pay'],
        4 => ['label' => 'Review & Confirm', 'description' => 'Review before you pay'],
        5 => ['label' => 'Confirmation', 'description' => 'Complete your subscription'],
    ];
@endphp

<div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm sm:p-6 lg:rounded-[10px]">
    <ol class="flex items-start justify-between">
        @foreach ($steps as $number => $step)
            <li
                @class([
                    'flex min-w-0 flex-1 items-center',
                    "after:mx-1.5 after:mt-4 after:h-0.5 after:min-w-4 after:flex-1 after:content-[''] sm:after:mx-3" => $number !== count($steps),
                    'after:bg-green-500' => $number < $current,
                    'after:bg-gray-200' => $number >= $current,
                ])
            >
                <div class="flex min-w-0 flex-col items-center text-center">
                    <span
                        @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold sm:h-10 sm:w-10',
                            'bg-green-500 text-white' => $number < $current,
                            'bg-primary-600 text-white ring-4 ring-primary-100' => $number === $current,
                            'bg-gray-100 text-gray-400' => $number > $current,
                        ])
                    >
                        @if ($number < $current)
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @else
                            {{ $number }}
                        @endif
                    </span>
                    <div class="mt-1.5 min-w-0">
                        <p @class([
                            'truncate text-[11px] font-bold leading-tight sm:text-xs',
                            'text-gray-900' => $number <= $current,
                            'text-gray-400' => $number > $current,
                        ])>
                            {{ $step['label'] }}
                        </p>
                        <p class="mt-0.5 text-[10px] font-semibold leading-tight text-gray-600">
                            @if ($number < $current)
                                Completed
                            @elseif ($number === $current)
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
