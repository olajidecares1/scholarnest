@php
    $school = $card->school;
    $template = $card->template;
    $primaryColor = $template?->primary_color ?? '#1d4ed8';
    $secondaryColor = $template?->secondary_color ?? '#111a35';
    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $cardWidth = $isLandscape ? '85.6mm' : '53.98mm';
    $cardHeight = $isLandscape ? '53.98mm' : '85.6mm';
    $motto = $school->website?->slogan_tagline ?: $school->website?->slogan;
    $qr = app(\App\Services\CodeImageGenerator::class)->qrCodeDataUri($card->verificationUrl(), 160);
    $defaultInstructions = "This card is the property of {$school->name}.\nIt must be worn at all times on campus.\nIt is non-transferable and must not be tampered with.\nReport loss or damage to the school office immediately.";
    $instructionLines = collect(explode("\n", $template?->instructions ?: $defaultInstructions))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
@endphp

<div
    class="id-card flex h-full flex-col overflow-hidden bg-white text-gray-900"
    style="width: {{ $cardWidth }}; height: {{ $cardHeight }}; border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px; font-family: 'Inter', sans-serif;"
>
    <div class="shrink-0 text-center text-white" style="background-color: {{ $secondaryColor }}; padding: 5px 6px 16px; border-top-left-radius: 3.5px; border-top-right-radius: 3.5px; border-bottom-left-radius: 50% 14px; border-bottom-right-radius: 50% 14px;">
        <div class="flex justify-center" style="padding-bottom: 3px;">
            <span class="rounded-full" style="width: 16px; height: 5px; background: linear-gradient(180deg, #d1d5db, #f3f4f6); box-shadow: inset 0 1px 1px rgba(0,0,0,0.15);"></span>
        </div>
        @if ($school->logoUrl())
            <img src="{{ $school->logoUrl() }}" class="mx-auto shrink-0 object-contain" style="width: 64px; height: 64px;">
        @endif
        <p class="mt-2 truncate text-[13px] font-extrabold uppercase leading-tight">{{ $school->name }}</p>
        @if ($motto)
            <p class="mt-0.5 truncate text-[7.5px] font-bold leading-tight text-white/90">{{ $motto }}</p>
        @endif
    </div>

    <div class="relative flex min-h-0 flex-1 flex-col gap-1 px-2 pb-1.5 pt-1">
        <div>
            <p class="text-center text-[7px] font-extrabold uppercase tracking-wide" style="color: {{ $secondaryColor }};">Instructions</p>
            <ul class="mt-1.5 space-y-1 text-[6px] font-bold leading-snug text-gray-600">
                @foreach ($instructionLines as $line)
                    <li class="flex items-start gap-1">
                        <span class="shrink-0">&bull;</span>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div style="border-top: 1px solid #dc2626; margin: 1px 0;"></div>

        <div class="flex flex-1 items-start gap-2">
            <div class="min-w-0 flex-1 space-y-0.5">
                <p class="text-[6px] font-bold" style="color: {{ $secondaryColor }};">If found, please return to:</p>
                @if ($school->website?->contact_address)
                    <p class="truncate text-[6px] text-gray-600">
                        <svg class="mr-1 inline-block h-[7px] w-[7px] align-[-0.5px]" viewBox="0 0 384 512" fill="{{ $primaryColor }}"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg>{{ $school->website->contact_address }}
                    </p>
                @endif
                @if ($school->website?->contact_phone)
                    <p class="truncate text-[6px] text-gray-600">
                        <svg class="mr-1 inline-block h-[7px] w-[7px] align-[-0.5px]" viewBox="0 0 512 512" fill="{{ $primaryColor }}"><path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z"/></svg>{{ $school->website->contact_phone }}
                    </p>
                @endif
                @if ($school->website?->contact_email)
                    <p class="truncate text-[6px] text-gray-600">
                        <svg class="mr-1 inline-block h-[7px] w-[7px] align-[-0.5px]" viewBox="0 0 512 512" fill="{{ $primaryColor }}"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></svg>{{ $school->website->contact_email }}
                    </p>
                @endif
                @if ($school->resolvedPublicHost())
                    <p class="truncate text-[6px] text-gray-600">
                        <svg class="mr-1 inline-block h-[7px] w-[7px] align-[-0.5px]" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="{{ $primaryColor }}"/><ellipse cx="10" cy="10" rx="3.2" ry="9" fill="white"/><rect x="1" y="8.5" width="18" height="3" fill="white"/></svg>{{ $school->resolvedPublicHost() }}
                    </p>
                @endif
            </div>
            <img src="{{ $qr }}" class="h-9 w-9 shrink-0" style="margin-left: 2px;" alt="QR Code">
        </div>

        <div class="flex shrink-0 flex-col items-center gap-0.5 border-t pt-1 text-center" style="border-color: {{ $secondaryColor }}33;">
            @if ($school->principalSignatureUrl())
                <img src="{{ $school->principalSignatureUrl() }}" class="h-5 object-contain">
            @endif
            <p class="text-[6px] font-bold leading-tight text-gray-700">{{ $school->principal_name ?: 'Principal' }}</p>
            <p class="text-[4.5px] leading-tight text-gray-400">Principal</p>
        </div>
    </div>

    <div class="shrink-0" style="background-color: {{ $secondaryColor }}; padding: 7px 4px 6px; border-top-left-radius: 50% 13px; border-top-right-radius: 50% 13px; margin-top: 5px;"></div>
</div>
