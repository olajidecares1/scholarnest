@php
    $school = $card->school;
    $template = $card->template;
    $primaryColor = $template?->primary_color ?? '#1d4ed8';
    $secondaryColor = $template?->secondary_color ?? '#111a35';
    $motto = $school->website?->slogan_tagline ?: $school->website?->slogan;

    // dompdf does not reliably honor CSS-only <img> sizing (including for
    // arbitrary uploaded raster images, which can render at a size wildly
    // different from any explicit width/height given) - every raster image
    // here is re-sampled to its exact display size server-side instead of
    // trusting dompdf's own image scaling (see _front.blade.php for the
    // full explanation).
    $images = app(\App\Services\CodeImageGenerator::class);
    $qr = $images->qrCodeDataUri($card->verificationUrl(), 108);

    $logoAbsolutePath = $school->logoAbsolutePath();
    // Contain (not crop-to-circle) so the logo keeps its true aspect ratio,
    // matching the front header's portrait logo treatment.
    $logoPath = $logoAbsolutePath ? $images->containedImageDataUri($logoAbsolutePath, 64, 64) : null;

    $signatureAbsolutePath = $school->principalSignatureAbsolutePath();
    $signature = $signatureAbsolutePath ? $images->containedImageDataUri($signatureAbsolutePath, 60, 16) : null;

    $svgIcon = fn (string $path, string $viewBox = '0 0 512 512') => 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'"><path fill="'.$primaryColor.'" d="'.$path.'"/></svg>'
    );
    $iconAddress = $svgIcon('M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512');
    $iconPhone = $svgIcon('M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z');
    $iconEmail = $svgIcon('M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z');
    $iconWebsite = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="'.$primaryColor.'"/><ellipse cx="10" cy="10" rx="3.2" ry="9" fill="white"/><rect x="1" y="8.5" width="18" height="3" fill="white"/></svg>');

    $defaultInstructions = "This card is the property of {$school->name}.\nIt must be worn at all times on campus.\nIt is non-transferable and must not be tampered with.\nReport loss or damage to the school office immediately.";
    $instructionLines = collect(explode("\n", $template?->instructions ?: $defaultInstructions))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();

    // A card needs BOTH a curved header and a curved footer here, and
    // dompdf has a confirmed bug where a second `border-radius: 50% ...`
    // curve anywhere later on the same page silently fails to paint once a
    // first one has already rendered - reliable alone, broken as soon as
    // both appear together. Both curves are pre-rendered raster bands
    // instead (see CodeImageGenerator::curvedBandDataUri()), which sidesteps
    // dompdf's border-radius handling entirely.
    $headerHeight = 130;
    $headerCurve = $images->curvedBandDataUri($secondaryColor, 204, $headerHeight, 'bottom', 17);
    $footerCurve = $images->curvedBandDataUri($secondaryColor, 204, 20, 'top', 13);
@endphp

<table class="pdf-card" style="border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px;">
    <tr>
        <td style="padding: 0;">
            <div style="background-image: url({{ $headerCurve }}); background-size: 100% 100%; background-repeat: no-repeat; width: 204px; height: {{ $headerHeight }}px; text-align: center; padding: 5px 6px 0; box-sizing: border-box; border-top-left-radius: 5px; border-top-right-radius: 5px;">
                <div style="text-align: center; padding-bottom: 3px;">
                    <div style="display: inline-block; width: 16px; height: 5px; border-radius: 999px; background-color: #d1d5db;"></div>
                </div>
                @if ($logoPath)
                    <img src="{{ $logoPath['uri'] }}" width="{{ $logoPath['width'] }}" height="{{ $logoPath['height'] }}" style="width: {{ $logoPath['width'] }}px; height: {{ $logoPath['height'] }}px;">
                @endif
                <div style="font-size: 13px; font-weight: bold; text-transform: uppercase; color: #ffffff; margin-top: 8px;">{{ $school->name }}</div>
                @if ($motto)
                    <div style="font-size: 7.5px; font-weight: bold; color: rgba(255,255,255,0.9); margin-top: 2px;">{{ $motto }}</div>
                @endif
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding: 3px 6px 2px;">
            <div style="text-align: center; color: {{ $secondaryColor }}; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px;">Instructions</div>
            <div style="font-size: 6px; font-weight: bold; color: #6b7280; line-height: 1.5; margin-top: 3px;">
                @foreach ($instructionLines as $line)
                    <div style="padding-left: 6px; text-indent: -6px; margin-top: 1px;">&bull;&nbsp; {{ $line }}</div>
                @endforeach
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding: 2px 6px 0;"><div style="border-top: 1px solid #dc2626;"></div></td>
    </tr>
    <tr>
        <td style="padding: 3px 6px;">
            <table style="width: 100%;">
                <tr>
                    <td style="vertical-align: top;">
                        <div style="font-size: 6px; font-weight: bold; color: {{ $secondaryColor }};">If found, please return to:</div>
                        @if ($school->website?->contact_address)
                            <div style="font-size: 6px; color: #6b7280; margin-top: 1px;"><img src="{{ $iconAddress }}" style="width: 7px; height: 7px; vertical-align: middle; margin-right: 3px;">{{ $school->website->contact_address }}</div>
                        @endif
                        @if ($school->website?->contact_phone)
                            <div style="font-size: 6px; color: #6b7280; margin-top: 1px;"><img src="{{ $iconPhone }}" style="width: 7px; height: 7px; vertical-align: middle; margin-right: 3px;">{{ $school->website->contact_phone }}</div>
                        @endif
                        @if ($school->website?->contact_email)
                            <div style="font-size: 6px; color: #6b7280; margin-top: 1px;"><img src="{{ $iconEmail }}" style="width: 7px; height: 7px; vertical-align: middle; margin-right: 3px;">{{ $school->website->contact_email }}</div>
                        @endif
                        @if ($school->resolvedPublicHost())
                            <div style="font-size: 6px; color: #6b7280; margin-top: 1px;"><img src="{{ $iconWebsite }}" style="width: 7px; height: 7px; vertical-align: middle; margin-right: 3px;">{{ $school->resolvedPublicHost() }}</div>
                        @endif
                    </td>
                    <td style="width: 40px; vertical-align: top; text-align: right;">
                        <img src="{{ $qr }}" width="36" height="36" style="width: 36px; height: 36px;" alt="QR Code">
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 3px 6px 5px; border-top: 1px solid {{ $secondaryColor }}33; text-align: center;">
            @if ($signature)
                <img src="{{ $signature['uri'] }}" width="{{ $signature['width'] }}" height="{{ $signature['height'] }}" style="width: {{ $signature['width'] }}px; height: {{ $signature['height'] }}px;">
            @endif
            <div style="font-size: 6px; font-weight: bold; color: #374151;">{{ $school->principal_name ?: 'Principal' }}</div>
            <div style="font-size: 4.5px; color: #9ca3af;">Principal</div>
        </td>
    </tr>
    <tr>
        <td style="padding: 5px 0 0;">
            <img src="{{ $footerCurve }}" width="204" height="20" style="width: 204px; height: 20px; display: block;">
        </td>
    </tr>
</table>
