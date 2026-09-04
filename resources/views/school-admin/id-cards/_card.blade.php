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
    // This design is the default every school edits into its own, so the
    // colours it falls back to are defined once, in App\Support\IdCardDesign,
    // rather than written out again in each of the four card faces.
    ['primary' => $primaryColor, 'secondary' => $secondaryColor, 'accent' => $accentColor]
        = \App\Support\IdCardDesign::colors($template);

    $badgeColor = $isStudent ? $accentColor : $primaryColor;
    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $cardWidth = $isLandscape ? '85.6mm' : '53.98mm';
    $cardHeight = $isLandscape ? '53.98mm' : '85.6mm';
    // Set in Settings on every plan, not only by schools with a public
    // website - see App\Support\SchoolMotto.
    $motto = \App\Support\SchoolMotto::for($school)->tagline;
    $images = app(\App\Services\CodeImageGenerator::class);
    $barcode = $images->barcodeDataUri($card->card_number, 220, 26);
@endphp

<div
    class="id-card relative flex h-full flex-col overflow-hidden bg-white text-gray-900"
    style="width: {{ $cardWidth }}; height: {{ $cardHeight }}; border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px; font-family: 'Inter', sans-serif;"
>
    {{-- The watermark: this school's own crest, behind everything.

         Fainter than the report card's, because a card is a tenth the size and
         the same opacity that reads as subtle on A4 reads as a smudge here.
         Skipped entirely when a school has uploaded no logo. --}}
    @if ($school->logoUrl())
        <span class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center" aria-hidden="true">
            <img src="{{ $school->logoUrl() }}" class="w-[72%] max-w-none object-contain" style="opacity: 0.04;" alt="">
        </span>
    @endif

    <div class="relative z-10 flex h-full flex-col">
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
                    <p class="truncate text-center text-[3.8px] font-semibold italic leading-tight" style="color: {{ $accentColor }};">{{ $motto }}</p>
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
        {{-- Portrait: the reference card, followed rather than approximated.
             Top to bottom - lanyard slot, crest beside the school name with
             the tagline under it, a red rule, the photograph, the holder's
             name, their badge, their details, the barcode, and a flat navy
             footer bar carrying the school's name. --}}

        {{-- SEGMENT 1 - the masthead.

             Three bands, in this order: a navy header carrying the lanyard
             slot, a red stripe across the full width, then the crest beside
             the school's name.

             The slot sits INSIDE the navy band rather than on white above it,
             and the red stripe sits directly under that band rather than
             under the crest. Both were the other way round before, which is
             what made the top of the card read differently from the reference
             even though the same pieces were present.

             Drawn rather than baked into a raster header image, so it stays
             sharp at print resolution and takes the school's own colour. --}}
        <div class="flex shrink-0 items-center justify-center" style="background-color: {{ $secondaryColor }}; height: 26px; border-top-left-radius: 3.5px; border-top-right-radius: 3.5px;">
            <span style="display: block; width: 64px; height: 11px; border-radius: 6px; background-color: #ffffff;"></span>
        </div>

        <div class="shrink-0" style="height: 5px; background-color: {{ $accentColor }};"></div>

        <div class="flex shrink-0 items-center" style="padding: 5px 7px 4px; gap: 5px;">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" class="shrink-0 object-contain" style="width: 40px; height: 40px;">
            @endif
            <div class="min-w-0 flex-1">
                {{-- Two lines allowed, not truncated. "Marvel Int' School" fits
                     on one; plenty of real school names do not, and a name cut
                     off mid-word is worse than a name that wraps. --}}
                <p class="line-clamp-2 text-[9.5px] font-extrabold uppercase leading-[1.05]" style="color: {{ $secondaryColor }}; font-family: Georgia, 'Times New Roman', serif;">{{ $school->name }}</p>
                @if ($motto)
                    <p class="line-clamp-2 text-[5.5px] font-semibold italic leading-tight" style="color: {{ $accentColor }}; margin-top: 2px;">{{ $motto }}</p>
                @endif
            </div>
        </div>

        {{-- SEGMENT 2 - the holder: photograph, name, badge.

             The photograph is a rounded rectangle inside a navy frame, with a
             thin white gap between the two - a frame around the picture rather
             than a border drawn on it. Roughly 41% of the card's width, which
             is what makes it read as a portrait rather than a thumbnail. --}}
        <div class="flex min-h-0 flex-1 flex-col items-center overflow-hidden" style="padding: 3px 7px 0;">
            <span class="shrink-0" style="width: 68px; height: 82px; border: 1.5px solid {{ $photoUrl ? $secondaryColor : '#e5e7eb' }}; border-radius: 8px; background-color: #ffffff; padding: 2px;">
                <span class="block h-full w-full overflow-hidden" style="border-radius: 6px; background-color: {{ $photoUrl ? '#ffffff' : '#f3f4f6' }};">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full w-full items-center justify-center text-lg font-bold text-gray-400">{{ $initials }}</span>
                    @endif
                </span>
            </span>

            {{-- Two lines rather than truncated, for the same reason the school
                 name wraps: "Chinedu Samuel Okafor" fits, and plenty of names
                 do not. --}}
            <p class="line-clamp-2 w-full text-center text-[9.5px] font-extrabold uppercase leading-[1.1]" style="color: {{ $secondaryColor }}; margin-top: 4px;">{{ $holder->fullName() }}</p>

            <span class="text-[6px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $badgeColor }}; padding: 1.5px 9px; border-radius: 3px; margin-top: 3px;">{{ $holderType->label() }}</span>
            {{-- SEGMENT 3 - the details.

                 Each row is a navy tile carrying a white icon, then the label,
                 then a colon in its own column, then the value - with a hair
                 rule between rows. The colon is a column rather than glued to
                 the value so every row's colon lines up whatever the labels
                 are, which is the thing that makes a list of five details read
                 as a table rather than as five sentences.

                 The rows themselves come from App\Support\IdCardFields, which
                 both this card and the printed one read, so the preview a
                 School Admin approves is the card that comes out of the
                 printer. --}}
            <div class="w-full" style="margin-top: 5px;">
                @foreach (\App\Support\IdCardFields::rows($card) as $index => $row)
                    <div class="flex items-center" @style(['gap: 4px', 'padding: 1.5px 1px', 'border-top: 0.5px solid #e5e7eb' => $index > 0])>
                        <span class="flex shrink-0 items-center justify-center" style="width: 10px; height: 10px; border-radius: 2px; background-color: {{ $secondaryColor }};">
                            @if ($row['icon'])
                                <img src="{{ $row['icon'] }}" style="width: 6px; height: 6px; display: block;" alt="">
                            @endif
                        </span>

                        <span class="shrink-0 text-[5.5px] font-bold uppercase" style="color: {{ $secondaryColor }}; width: 50px;">{{ $row['label'] }}</span>
                        <span class="shrink-0 text-[5.5px] font-bold" style="color: {{ $secondaryColor }};">:</span>
                        <span class="min-w-0 flex-1 truncate text-[5.5px] font-medium" style="color: {{ $secondaryColor }};">{{ $row['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- The barcode runs the width of the card, unframed. --}}
        <div class="shrink-0" style="padding: 3px 7px 4px;">
            <img src="{{ $barcode }}" class="w-full object-contain" style="height: 16px; display: block;">
        </div>

        {{-- A red hairline, then a flat navy bar - not a wave. --}}
        <div class="shrink-0" style="height: 2px; background-color: {{ $accentColor }};"></div>

        <div class="shrink-0 text-center text-white" style="background-color: {{ $secondaryColor }}; padding: 4px;">
            <p class="truncate text-[7.5px] font-bold uppercase tracking-wide">{{ $school->name }}</p>
        </div>
    @endif
    </div>
</div>
