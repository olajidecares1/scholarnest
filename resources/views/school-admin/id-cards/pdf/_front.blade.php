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
    // Defined once, in App\Support\IdCardDesign, see _card.blade.php.
    ['primary' => $primaryColor, 'secondary' => $secondaryColor, 'accent' => $accentColor]
        = \App\Support\IdCardDesign::colors($template);
    $badgeColor = $isStudent ? $accentColor : $primaryColor;
    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    // Set in Settings on every plan, not only by schools with a public
    // website, see App\Support\SchoolMotto.
    $motto = \App\Support\SchoolMotto::for($school)->tagline;

    // dompdf's `enable_remote` option is off, so it can only read images via
    // local filesystem path/data-URI, never http(s) URLs.
    $logoAbsolutePath = $school->logoAbsolutePath();
    $photoAbsolutePath = $holder->photoAbsolutePath();
    $initials = Str::of($holder->first_name)->substr(0, 1)->upper().Str::of($holder->last_name)->substr(0, 1)->upper();

    // Generated at the exact pixel size it will be displayed at (rather
    // than scaled via CSS), dompdf does not reliably honor width:100% (or
    // any CSS override) on <img>, so mismatched intrinsic/display sizes
    // cause it to render at its native size and blow out the tiny card
    // canvas, spilling content onto extra pages.
    // 180px: the card is 204 wide, less its 1.5px border each side and the
    // 7px cell padding each side. The reference barcode runs the full width
    // between those margins, unframed.
    $barcodeWidth = $isLandscape ? 130 : 180;
    $barcodeHeight = $isLandscape ? 14 : 16;
    $images = app(\App\Services\CodeImageGenerator::class);
    $barcode = $images->barcodeDataUri($card->card_number, $barcodeWidth, $barcodeHeight);

    // Same reasoning as the barcode above, but for real uploaded photos,
    // dompdf can render an arbitrary uploaded raster image at a size wildly
    // different from any explicit width/height given, so it's re-sampled to
    // its exact target pixel size server-side instead of trusting dompdf's
    // own image scaling.
    $logoSize = 28;
    $logoPath = ($logoAbsolutePath && $isLandscape) ? $images->croppedImageDataUri($logoAbsolutePath, $logoSize, $logoSize) : null;
    // Portrait keeps the logo's true aspect ratio (no crop-to-square) since
    // it sits beside the school name rather than inside a circular frame.
    // 58px square: the crest is a substantial mark on the reference masthead,
    // set against the school name rather than tucked beside it.
    $portraitLogo = ($logoAbsolutePath && ! $isLandscape) ? $images->containedImageDataUri($logoAbsolutePath, 40, 40) : null;
    // A third of the card's width. Measured off the reference it came out at
    // 84px, but on a rendered card that read as too heavy against the details
    // below it, so it was brought down by eye.
    $photoWidth = $isLandscape ? 30 : 68;
    $photoHeight = $isLandscape ? 34 : 82;
    $photoPath = $photoAbsolutePath ? $images->croppedImageDataUri($photoAbsolutePath, $photoWidth, $photoHeight) : null;
@endphp

<table class="pdf-card" style="border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px;">
    @if ($isLandscape)
        <tr>
            <td style="width: 30%; vertical-align: middle; text-align: center; padding: 4px; border-right: 1px solid {{ $secondaryColor }}33;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" width="28" height="28" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid {{ $primaryColor }};">
                @endif
                <div style="font-size: 5.5px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; margin-top: 2px;">{{ $school->name }}</div>
                @if ($motto)
                    <div style="font-size: 3.8px; font-weight: 600; font-style: italic; color: {{ $primaryColor }};">{{ $motto }}</div>
                @endif
                @if ($photoPath)
                    <img src="{{ $photoPath }}" width="30" height="34" style="width: 30px; height: 34px; object-fit: cover; border: 1px solid {{ $secondaryColor }}; margin-top: 3px;">
                @else
                    <div style="width: 30px; height: 34px; border: 1px solid {{ $secondaryColor }}; text-align: center; line-height: 34px; font-size: 8px; color: #9ca3af; margin: 3px auto 0;">{{ $initials }}</div>
                @endif
                <div style="display: inline-block; margin-top: 2px; padding: 1px 4px; border-radius: 3px; background-color: {{ $badgeColor }}; color: #ffffff; font-size: 3.8px; font-weight: bold; text-transform: uppercase;">{{ $holderType->label() }}</div>
            </td>
            <td style="vertical-align: middle; padding: 4px 5px;">
                <div style="font-size: 7px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }};">{{ $holder->fullName() }}</div>
                <table style="width: 100%; margin-top: 2px;">
                    @include('school-admin.id-cards.pdf._info-rows', ['secondaryColor' => $secondaryColor, 'idLabel' => $idLabel, 'identifier' => $identifier, 'fieldLabel' => $fieldLabel, 'secondaryLine' => $secondaryLine, 'holder' => $holder, 'isStudent' => $isStudent, 'school' => $school, 'template' => $template, 'card' => $card, 'fontSize' => '5px', 'colonGap' => 2])
                </table>
                <img src="{{ $barcode }}" width="{{ $barcodeWidth }}" height="{{ $barcodeHeight }}" style="width: {{ $barcodeWidth }}px; height: {{ $barcodeHeight }}px; margin-top: 2px;">
            </td>
        </tr>
    @else
        {{-- Portrait: the reference card, followed rather than approximated.
        Top to bottom, lanyard slot, crest beside the school name with the
        tagline under it, a red rule, the photograph, the holder's name, their
        badge, their details, the barcode, and a flat navy footer bar. --}}
        {{-- SEGMENT 1, the masthead: a navy band carrying the lanyard slot, a
        red stripe across the full width, then the crest beside the school's
        name. The slot sits inside the navy band, and the stripe under that
        band rather than under the crest. --}}
        <tr>
            <td style="padding: 0;">
                <div style="background-color: {{ $secondaryColor }}; height: 26px; text-align: center; border-top-left-radius: 3.5px; border-top-right-radius: 3.5px;">
                    <div style="width: 64px; height: 11px; border-radius: 6px; background-color: #ffffff; margin: 7px auto 0;"></div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding: 0;">
                <div style="height: 5px; background-color: {{ $accentColor }}; font-size: 0; line-height: 0;">&nbsp;</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 5px 7px 4px;">
                <table style="width: 100%;">
                    <tr>
                        @if ($portraitLogo)
                            <td style="width: {{ $portraitLogo['width'] }}px; vertical-align: middle;">
                                <img src="{{ $portraitLogo['uri'] }}" width="{{ $portraitLogo['width'] }}" height="{{ $portraitLogo['height'] }}" style="width: {{ $portraitLogo['width'] }}px; height: {{ $portraitLogo['height'] }}px;">
                            </td>
                        @endif
                        <td style="vertical-align: middle; padding-left: 5px; white-space: nowrap;">
                            {{-- white-space is allowed to wrap here: plenty of
                            real school names are longer than "Marvel Int'
                            School", and a name cut off mid-word is worse than
                            one that takes two lines. --}}
                            <div style="font-size: 9.5px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; font-family: Georgia, 'Times New Roman', serif; line-height: 1.05;">{{ $school->name }}</div>
                            @if ($motto)
                                <div style="font-size: 5.5px; font-weight: 600; font-style: italic; color: {{ $accentColor }}; margin-top: 2px; line-height: 1.15;">{{ $motto }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding: 3px 7px 0;">
                {{-- SEGMENT 2, the holder: photograph, name, badge. The
                photograph is a rounded rectangle inside a navy frame with a
                thin white gap between the two, which is a frame around the
                picture rather than a border drawn on it. --}}
                <div>
                    @if ($photoPath)
                        <div style="display: inline-block; border: 1.5px solid {{ $secondaryColor }}; border-radius: 8px; padding: 2px; background-color: #ffffff; font-size: 0; line-height: 0;">
                            <img src="{{ $photoPath }}" width="{{ $photoWidth }}" height="{{ $photoHeight }}" style="width: {{ $photoWidth }}px; height: {{ $photoHeight }}px; object-fit: cover; border-radius: 6px; display: block;">
                        </div>
                    @else
                        <div style="width: {{ $photoWidth }}px; height: {{ $photoHeight }}px; border: 1.5px solid #e5e7eb; border-radius: 8px; text-align: center; line-height: {{ $photoHeight }}px; font-size: 18px; color: #9ca3af; margin: 0 auto; background-color: #f3f4f6;">{{ $initials }}</div>
                    @endif
                    <div style="font-size: 9.5px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; margin-top: 4px; line-height: 1.1;">{{ $holder->fullName() }}</div>
                    <div style="display: inline-block; margin-top: 3px; padding: 1.5px 9px; border-radius: 3px; background-color: {{ $badgeColor }}; color: #ffffff; font-size: 6px; font-weight: bold; text-transform: uppercase;">{{ $holderType->label() }}</div>
                    {{-- SEGMENT 3, the details. A navy tile with a white icon,
                    the label, a colon in its own column, then the value, with a
                    hair rule between rows. Rows come from
                    App\Support\IdCardFields, which the on-screen card reads too,
                    so the preview a School Admin approves is what prints. --}}
                    <table style="margin: 5px auto 0; text-align: left; width: 100%;">
                        @foreach (\App\Support\IdCardFields::rows($card) as $index => $row)
                            <tr>
                                <td style="width: 12px; padding: 1.5px 0; vertical-align: middle; {{ $index > 0 ? 'border-top: 0.5px solid #e5e7eb;' : '' }}">
                                    <div style="width: 10px; height: 10px; border-radius: 2px; background-color: {{ $secondaryColor }}; text-align: center;">
                                        @if ($row['icon'])
                                            <img src="{{ $row['icon'] }}" width="6" height="6" style="width: 6px; height: 6px; margin-top: 2px;">
                                        @endif
                                    </div>
                                </td>
                                <td style="padding: 1.5px 0 2.5px 5px; font-size: 5.5px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; vertical-align: middle; {{ $index > 0 ? 'border-top: 0.5px solid #e5e7eb;' : '' }}">{{ $row['label'] }}</td>
                                <td style="padding: 1.5px 5px; font-size: 5.5px; font-weight: bold; color: {{ $secondaryColor }}; vertical-align: middle; {{ $index > 0 ? 'border-top: 0.5px solid #e5e7eb;' : '' }}">:</td>
                                <td style="padding: 1.5px 0; font-size: 5.5px; font-weight: 500; color: {{ $secondaryColor }}; vertical-align: middle; {{ $index > 0 ? 'border-top: 0.5px solid #e5e7eb;' : '' }}">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            {{-- The barcode runs the width of the card, unframed. --}}
            <td style="padding: 3px 7px 4px; text-align: center;">
                <img src="{{ $barcode }}" width="{{ $barcodeWidth }}" height="{{ $barcodeHeight }}" style="width: {{ $barcodeWidth }}px; height: {{ $barcodeHeight }}px; display: block; margin: 0 auto;">
            </td>
        </tr>
        <tr>
            {{-- A red hairline, then a flat navy bar, not a wave. --}}
            <td style="padding: 0;">
                <div style="height: 2px; background-color: {{ $accentColor }}; font-size: 0; line-height: 0;">&nbsp;</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 0;">
                <div style="background-color: {{ $secondaryColor }}; text-align: center; padding: 4px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ $school->name }}</div>
                </div>
            </td>
        </tr>
    @endif
</table>
