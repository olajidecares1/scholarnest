@php
    $isActive = $card->status === \App\Enums\IssuedIdCardStatus::Active;
    $identifier = $holder instanceof \App\Models\Student ? $holder?->admission_number : $holder?->staff_number;
    $secondaryLine = $holder instanceof \App\Models\Student ? $holder?->class_name : ($holder?->department ?? $holder?->role?->label());
@endphp

<x-auth-layout :title="'Verify ID Card - '.$school->name" simple>
    <x-auth-card>
        <div class="text-center">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" class="mx-auto h-12 w-12 rounded-[6px] object-cover">
            @endif
            <p class="mt-2 text-sm font-semibold text-gray-500">{{ $school->name }}</p>
        </div>

        @if (! $isActive)
            <div class="mt-6 rounded-[8px] bg-red-50 p-4 text-center">
                <p class="text-sm font-bold text-red-700">This ID card has been revoked.</p>
                <p class="mt-1 text-xs text-red-600">Card {{ $card->card_number }} is no longer valid.</p>
            </div>
        @elseif (! $holder)
            <div class="mt-6 rounded-[8px] bg-amber-50 p-4 text-center">
                <p class="text-sm font-bold text-amber-700">This card's holder record could not be found.</p>
            </div>
        @else
            <div class="mt-6 rounded-[8px] bg-green-50 p-4 text-center">
                <p class="text-sm font-bold text-green-700">Valid ID Card</p>
            </div>

            <div class="mt-6 flex items-center gap-4">
                @if ($holder->photoUrl())
                    <img src="{{ $holder->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700">
                        {{ Str::of($holder->first_name)->substr(0, 1)->upper() }}{{ Str::of($holder->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-lg font-bold text-gray-900">{{ $holder->fullName() }}</p>
                    <p class="text-sm text-gray-500">{{ $card->holder_type->label() }} &middot; {{ $identifier }}</p>
                    @if ($secondaryLine)
                        <p class="text-sm text-gray-500">{{ $secondaryLine }}</p>
                    @endif
                </div>
            </div>

            <dl class="mt-6 space-y-2 border-t border-gray-100 pt-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Card Number</dt>
                    <dd class="font-semibold text-gray-900">{{ $card->card_number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Issued</dt>
                    <dd class="font-semibold text-gray-900">{{ $card->issued_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        @endif
    </x-auth-card>
</x-auth-layout>
