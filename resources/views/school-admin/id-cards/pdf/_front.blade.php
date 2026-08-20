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
    $primaryColor = $template?->primary_color ?? '#1d4ed8';
    $secondaryColor = $template?->secondary_color ?? '#111a35';
    // The reference design always marks students with a fixed red badge
    // regardless of the school's configured brand colours - only staff
    // badges use the school's own primary colour.
    $badgeColor = $isStudent ? '#dc2626' : $primaryColor;
    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $motto = $school->website?->slogan_tagline ?: $school->website?->slogan;

    // dompdf's `enable_remote` option is off, so it can only read images via
    // local filesystem path/data-URI, never http(s) URLs.
    $logoAbsolutePath = $school->logoAbsolutePath();
    $photoAbsolutePath = $holder->photoAbsolutePath();
    $initials = Str::of($holder->first_name)->substr(0, 1)->upper().Str::of($holder->last_name)->substr(0, 1)->upper();

    // Generated at the exact pixel size it will be displayed at (rather
    // than scaled via CSS) - dompdf does not reliably honor width:100% (or
    // any CSS override) on <img>, so mismatched intrinsic/display sizes
    // cause it to render at its native size and blow out the tiny card
    // canvas, spilling content onto extra pages.
    $barcodeWidth = $isLandscape ? 130 : 190;
    $barcodeHeight = $isLandscape ? 14 : 20;
    $images = app(\App\Services\CodeImageGenerator::class);
    $barcode = $images->barcodeDataUri($card->card_number, $barcodeWidth, $barcodeHeight);

    // Same reasoning as the barcode above, but for real uploaded photos -
    // dompdf can render an arbitrary uploaded raster image at a size wildly
    // different from any explicit width/height given, so it's re-sampled to
    // its exact target pixel size server-side instead of trusting dompdf's
    // own image scaling.
    $logoSize = 28;
    $logoPath = ($logoAbsolutePath && $isLandscape) ? $images->croppedImageDataUri($logoAbsolutePath, $logoSize, $logoSize) : null;
    // Portrait keeps the logo's true aspect ratio (no crop-to-square) since
    // it sits beside the school name rather than inside a circular frame.
    $portraitLogo = ($logoAbsolutePath && ! $isLandscape) ? $images->containedImageDataUri($logoAbsolutePath, 48, 44) : null;
    $photoWidth = $isLandscape ? 30 : 58;
    $photoHeight = $isLandscape ? 34 : 64;
    $photoPath = $photoAbsolutePath ? $images->croppedImageDataUri($photoAbsolutePath, $photoWidth, $photoHeight) : null;
    $headerGraphic = $isLandscape ? null : $images->idCardHeaderDataUri($secondaryColor);
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
        {{-- Portrait: the reference master template. Header graphic (navy
        corner swooshes + pill slot) is a pre-rendered raster image, not CSS
        shapes - see CodeImageGenerator::idCardHeaderDataUri(). Below it: a
        horizontal logo-left/text-right block (never centered/stacked),
        with a large, prominent logo and bold name/motto. Body:
        photo/name/badge/info. Footer: navy wave. --}}
        <tr>
            <td style="padding: 0;">
                <img src="{{ $headerGraphic }}" width="204" height="38" style="width: 204px; height: 38px; display: block;">
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 5px 2px;">
                <table style="width: 100%;">
                    <tr>
                        @if ($portraitLogo)
                            <td style="width: {{ $portraitLogo['width'] }}px; vertical-align: middle;">
                                <img src="{{ $portraitLogo['uri'] }}" width="{{ $portraitLogo['width'] }}" height="{{ $portraitLogo['height'] }}" style="width: {{ $portraitLogo['width'] }}px; height: {{ $portraitLogo['height'] }}px;">
                            </td>
                        @endif
                        <td style="vertical-align: middle; padding-left: 5px; white-space: nowrap;">
                            <div style="font-size: 10px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; font-family: Georgia, 'Times New Roman', serif; white-space: nowrap;">{{ $school->name }}</div>
                            @if ($motto)
                                <div style="font-size: 6px; font-weight: bold; color: {{ $primaryColor }}; white-space: nowrap;">{{ $motto }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding: 2px 6px 4px;">
                <div>
                    @if ($photoPath)
                        <img src="{{ $photoPath }}" width="58" height="64" style="width: 58px; height: 64px; object-fit: cover; border: 2px solid {{ $secondaryColor }}; border-radius: 4px;">
                    @else
                        <div style="width: 58px; height: 64px; border: 2px solid #e5e7eb; border-radius: 4px; text-align: center; line-height: 64px; font-size: 13px; color: #9ca3af; margin: 0 auto; background-color: #f3f4f6;">{{ $initials }}</div>
                    @endif
                    <div style="font-size: 7.5px; font-weight: bold; text-transform: uppercase; color: {{ $secondaryColor }}; margin-top: 2px;">{{ $holder->fullName() }}</div>
                    <div style="display: inline-block; margin-top: 1px; padding: 1px 5px; border-radius: 6px; background-color: {{ $badgeColor }}; color: #ffffff; font-size: 5px; font-weight: bold; text-transform: uppercase;">{{ $holderType->label() }}</div>

                    <table style="margin: 2px auto 0; text-align: left;">
                        @include('school-admin.id-cards.pdf._info-rows', ['secondaryColor' => $secondaryColor, 'idLabel' => $idLabel, 'identifier' => $identifier, 'fieldLabel' => $fieldLabel, 'secondaryLine' => $secondaryLine, 'holder' => $holder, 'isStudent' => $isStudent, 'school' => $school, 'template' => $template, 'card' => $card, 'fontSize' => '5.5px', 'colonGap' => 5])
                    </table>

                    <img src="{{ $barcode }}" width="{{ $barcodeWidth }}" height="{{ $barcodeHeight }}" style="width: {{ $barcodeWidth }}px; height: {{ $barcodeHeight }}px; margin-top: 3px;">
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding: 0;">
                <div style="background-color: {{ $secondaryColor }}; text-align: center; padding: 7px 4px 6px; border-top-left-radius: 50% 13px; border-top-right-radius: 50% 13px; margin-top: 5px;">
                    <div style="font-size: 6.5px; font-weight: bold; color: #ffffff; text-transform: uppercase;">{{ $school->name }}</div>
                </div>
            </td>
        </tr>
    @endif
</table>
