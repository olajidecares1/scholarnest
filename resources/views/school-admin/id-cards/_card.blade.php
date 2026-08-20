@php
    $holderType = $card->holder_type;
    $holder = $card->holder();
    $school = $card->school;
    $template = $card->template;
    $isStudent = $holderType === \App\Enums\IdCardHolderType::Student;
    $identifier = $isStudent ? $holder->admission_number : $holder->staff_number;
    $idLabel = $isStudent ? 'Admission No.' : 'Staff ID';
    $secondaryLine = $isStudent ? $holder->class_name : ($holder->department ?? $holder->role->label());
    $fieldLabel = $isStudent ? 'Class' : 'Department';
    $photoUrl = $holder->photoUrl();
    $initials = Str::of($holder->first_name)->substr(0, 1)->upper().Str::of($holder->last_name)->substr(0, 1)->upper();
    $primaryColor = $template?->primary_color ?? '#1d4ed8';
    $secondaryColor = $template?->secondary_color ?? '#111a35';
    // The reference design always marks students with a fixed red badge
    // regardless of the school's configured brand colours - only staff
    // badges use the school's own primary colour.
    $badgeColor = $isStudent ? '#dc2626' : $primaryColor;
    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $cardWidth = $isLandscape ? '85.6mm' : '53.98mm';
    $cardHeight = $isLandscape ? '53.98mm' : '85.6mm';
    $motto = $school->website?->slogan_tagline ?: $school->website?->slogan;
    $images = app(\App\Services\CodeImageGenerator::class);
    $barcode = $images->barcodeDataUri($card->card_number, 220, 26);
    $headerGraphic = $images->idCardHeaderDataUri($secondaryColor);
@endphp

<div
    class="id-card flex h-full flex-col overflow-hidden bg-white text-gray-900"
    style="width: {{ $cardWidth }}; height: {{ $cardHeight }}; border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px; font-family: 'Inter', sans-serif;"
>
    @if ($isLandscape)
        <div class="relative flex min-h-0 flex-1 flex-row">
            {{-- hole punch, positioned on the short left edge for landscape cards --}}
            <div class="absolute left-1.5 top-1/2 -translate-y-1/2 z-10 rounded-full bg-gray-300" style="width: 5px; height: 5px;"></div>

            <div class="flex w-[30%] shrink-0 flex-col items-center justify-center gap-1 border-r px-1.5 py-2" style="border-color: {{ $secondaryColor }}33;">
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" class="h-6 w-6 shrink-0 rounded-full object-cover" style="border: 1px solid {{ $primaryColor }};">
                @endif
                <p class="text-center text-[5.5px] font-extrabold uppercase leading-tight" style="color: {{ $secondaryColor }};">{{ $school->name }}</p>
                @if ($motto)
                    <p class="truncate text-center text-[3.8px] font-semibold italic leading-tight" style="color: {{ $primaryColor }};">{{ $motto }}</p>
                @endif

                <span class="flex shrink-0 items-center justify-center overflow-hidden rounded-[3px] border" style="width: 30px; height: 34px; border-color: {{ $secondaryColor }}; margin-top: 2px;">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-[8px] font-bold text-gray-400">{{ $initials }}</span>
                    @endif
                </span>
                <span class="mt-0.5 rounded-full px-1.5 py-0.5 text-[3.8px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $badgeColor }};">{{ $holderType->label() }}</span>
            </div>

            <div class="flex flex-1 flex-col justify-center gap-1 px-1.5 py-1.5">
                <p class="truncate text-[7px] font-extrabold uppercase leading-tight" style="color: {{ $secondaryColor }};">{{ $holder->fullName() }}</p>

                <div class="w-full grid grid-cols-[auto_1fr] gap-x-1 gap-y-0.5 text-[5px]">
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">{{ $idLabel }}</span>
                    <span class="truncate font-semibold text-gray-700">: {{ $identifier }}</span>

                    @if ($secondaryLine)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">{{ $fieldLabel }}</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $secondaryLine }}</span>
                    @endif

                    @if ($holder->date_of_birth)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">D.O.B</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $holder->date_of_birth->format('jS F, Y') }}</span>
                    @endif

                    @if ($isStudent && $holder->house)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">House</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $holder->house }}</span>
                    @endif

                    @if ($school->current_session)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Session</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $school->current_session }}</span>
                    @endif

                    @if ($template?->show_blood_group && $holder->blood_group)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Blood Group</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $holder->blood_group }}</span>
                    @endif

                    @if ($card->expiry_date)
                        <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Expires</span>
                        <span class="truncate font-semibold text-gray-700">: {{ $card->expiry_date->format('jS F, Y') }}</span>
                    @endif
                </div>

                <img src="{{ $barcode }}" class="mt-1 h-4 w-full object-contain">
            </div>
        </div>
    @else
        {{-- Portrait: the reference master template. Header graphic (navy
        corner swooshes + pill slot) is a pre-rendered raster image, not CSS
        shapes - see CodeImageGenerator::idCardHeaderDataUri(). Below it: a
        horizontal logo-left/text-right block (never centered/stacked),
        with a large, prominent logo and bold name/motto. Body:
        photo/name/badge/info. Footer: navy wave. --}}
        <img src="{{ $headerGraphic }}" width="204" height="38" class="shrink-0" style="width: 204px; height: 38px;" alt="">

        <div class="flex shrink-0 items-center" style="padding: 4px 5px 2px; gap: 5px;">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" class="shrink-0 object-contain" style="width: 48px; height: 48px;">
            @endif
            <div class="min-w-0 flex-1">
                <p class="truncate text-[10px] font-extrabold uppercase leading-tight" style="color: {{ $secondaryColor }}; font-family: Georgia, 'Times New Roman', serif;">{{ $school->name }}</p>
                @if ($motto)
                    <p class="truncate text-[6px] font-bold leading-tight" style="color: {{ $primaryColor }};">{{ $motto }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-1 flex-col items-center gap-1 px-2" style="margin-top: 2px;">
            <span class="shrink-0 overflow-hidden rounded-[4px] border-2" style="width: 68px; height: 78px; border-color: {{ $photoUrl ? $secondaryColor : '#e5e7eb' }}; background-color: {{ $photoUrl ? '#ffffff' : '#f3f4f6' }};">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center text-base font-bold text-gray-400">{{ $initials }}</span>
                @endif
            </span>

            <p class="mt-0.5 truncate text-center text-[8px] font-extrabold uppercase leading-tight" style="color: {{ $secondaryColor }};">{{ $holder->fullName() }}</p>

            <span class="rounded-[10px] px-2 py-0.5 text-[5.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $badgeColor }};">{{ $holderType->label() }}</span>

            {{-- Label / colon / value are three explicit grid columns (not a
            single label+value pair) so the colon always aligns and there's a
            deliberate, consistent gap on both sides of it regardless of how
            long any individual label or value is. --}}
            <div class="mt-1.5 grid grid-cols-[auto_auto_1fr] text-[6px]" style="column-gap: 6px; row-gap: 2px;">
                <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">{{ $idLabel }}</span>
                <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $identifier }}</span>

                @if ($secondaryLine)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">{{ $fieldLabel }}</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $secondaryLine }}</span>
                @endif

                @if ($holder->date_of_birth)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">D.O.B</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $holder->date_of_birth->format('jS F, Y') }}</span>
                @endif

                @if ($isStudent && $holder->house)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">House</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $holder->house }}</span>
                @endif

                @if ($school->current_session)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Session</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $school->current_session }}</span>
                @endif

                @if ($template?->show_blood_group && $holder->blood_group)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Blood Group</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $holder->blood_group }}</span>
                @endif

                @if ($card->expiry_date)
                    <span class="font-bold uppercase" style="color: {{ $secondaryColor }};">Expires</span>
                    <span class="font-bold" style="color: {{ $secondaryColor }};">:</span>
                    <span class="truncate font-medium" style="color: {{ $secondaryColor }};">{{ $card->expiry_date->format('jS F, Y') }}</span>
                @endif
            </div>

            <img src="{{ $barcode }}" class="mt-1.5 h-7 object-contain" style="width: 90%;">
        </div>

        <div class="shrink-0 text-center text-white" style="background-color: {{ $secondaryColor }}; padding: 7px 4px 6px; border-top-left-radius: 50% 13px; border-top-right-radius: 50% 13px; margin-top: 5px;">
            <p class="truncate text-[6.5px] font-bold uppercase tracking-wide">{{ $school->name }}</p>
        </div>
    @endif
</div>
