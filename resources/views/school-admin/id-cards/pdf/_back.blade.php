@php
    $school = $card->school;
    $template = $card->template;
    // Defined once, in App\Support\IdCardDesign, see _card.blade.php.
    ['primary' => $primaryColor, 'secondary' => $secondaryColor, 'accent' => $accentColor]
        = \App\Support\IdCardDesign::colors($template);

    // Set in Settings on every plan, not only by schools with a public
    // website, see App\Support\SchoolMotto.
    $motto = \App\Support\SchoolMotto::for($school)->tagline;

    // The school's own address, on every plan, see App\Support\SchoolContact.
    $contact = \App\Support\SchoolContact::for($school);

    // dompdf does not reliably honor CSS-only <img> sizing (including for
    // arbitrary uploaded raster images, which can render at a size wildly
    // different from any explicit width/height given), every raster image
    // here is re-sampled to its exact display size server-side instead of
    // trusting dompdf's own image scaling (see _front.blade.php for the
    // full explanation).
    $images = app(\App\Services\CodeImageGenerator::class);
    $qr = $images->qrCodeDataUri($card->verificationUrl(), 120);

    $logoAbsolutePath = $school->logoAbsolutePath();
    // Contain (not crop-to-circle) so the logo keeps its true aspect ratio,
    // matching the front header's portrait logo treatment.
    $logoPath = $logoAbsolutePath ? $images->containedImageDataUri($logoAbsolutePath, 52, 52) : null;

    // School Admin is the Principal, so this is their own registered
    // signature, already rendered gold, see App\Support\PrincipalSignature.
    $principalSignature = \App\Support\PrincipalSignature::for($school);
    $signatureAbsolutePath = $principalSignature?->absolutePath();
    $signature = $signatureAbsolutePath ? $images->containedImageDataUri($signatureAbsolutePath, 62, 15) : null;

    // The school's official stamp, sized to fit beside the signature.
    $stampAbsolutePath = $school->stampAbsolutePath();
    $stamp = $stampAbsolutePath ? $images->containedImageDataUri($stampAbsolutePath, 60, 26) : null;

    // A red disc with a white glyph inside it, as the reference draws each
    // contact line. The disc is painted into the SVG rather than applied as a
    // CSS background, because dompdf will neither colour nor clip an inline
    // one, the same reason these are data URIs at all.
    $discIcon = fn (string $path, string $viewBox = '0 0 512 512') => 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-96 -96 704 704">'
        .'<circle cx="256" cy="256" r="352" fill="'.$accentColor.'"/>'
        .'<svg viewBox="'.$viewBox.'" x="96" y="96" width="320" height="320">'
        .'<path fill="#ffffff" d="'.$path.'"/></svg>'
        .'</svg>'
    );

    $iconPerson = $discIcon('M406.5 399.6C387.4 352.9 341.5 320 288 320H224c-53.5 0-99.4 32.9-118.5 79.6C69.9 362.2 48 311.7 48 256C48 141.1 141.1 48 256 48s208 93.1 208 208c0 55.7-21.9 106.2-57.5 143.6zM256 288a72 72 0 1 0 0-144 72 72 0 1 0 0 144z');
    $iconAddress = $discIcon('M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512');
    $iconPhone = $discIcon('M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z');
    $iconEmail = $discIcon('M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z');
    $iconWebsite = $discIcon('M256 0a256 256 0 1 0 0 512A256 256 0 1 0 256 0zM48 256h80c0-38 5-73 13-102H63c-9 31-15 65-15 102zm15 134h78c-8-29-13-64-13-102H48c0 38 6 72 15 102zM256 48c-18 0-45 27-63 82h126c-18-55-45-82-63-82zm-79 130c-9 29-15 64-15 102s6 73 15 102h158c9-29 15-64 15-102s-6-73-15-102H177zm16 262c18 55 45 82 63 82s45-27 63-82H193zm190-50h78c9-30 15-64 15-102h-80c0 38-5 73-13 102zm13-134h80c0-37-6-71-15-102h-78c8 29 13 64 13 102zM440 96h-64c9 26 15 55 19 86h63c-4-31-10-60-18-86zM136 96H72c-8 26-14 55-18 86h63c4-31 10-60 19-86z');

    $instructionLines = collect(explode("\n", $template?->instructions ?: \App\Support\IdCardDesign::instructions($school)))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();

    // The curved header and footer bands this used to draw are gone: the
    // reference back is a solid navy panel with a straight lower edge. That
    // removed both pre-rendered raster curves, and with them the dompdf
    // border-radius bug they existed to work around.
    $headerHeight = 122;
@endphp

<table class="pdf-card" style="border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px; background-color: #f7f7f8;">
    {{-- SEGMENT 1, the navy crown: lanyard slot, crest, school name, tagline,
    closed off by the same red stripe the front carries. --}}
    <tr>
        <td style="padding: 0;">
            <div style="background-color: {{ $secondaryColor }}; width: 204px; height: {{ $headerHeight }}px; text-align: center; padding: 6px 7px 0; box-sizing: border-box; border-top-left-radius: 3.5px; border-top-right-radius: 3.5px;">
                <div style="text-align: center; padding-bottom: 5px;">
                    <div style="display: inline-block; width: 64px; height: 11px; border-radius: 6px; background-color: #ffffff;"></div>
                </div>
                @if ($logoPath)
                    <img src="{{ $logoPath['uri'] }}" width="{{ $logoPath['width'] }}" height="{{ $logoPath['height'] }}" style="width: {{ $logoPath['width'] }}px; height: {{ $logoPath['height'] }}px;">
                @endif
                <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; color: #ffffff; margin-top: 4px; font-family: Georgia, 'Times New Roman', serif;">{{ $school->name }}</div>
                @if ($motto)
                    <div style="font-size: 5.5px; font-weight: 600; color: rgba(255,255,255,0.9); margin-top: 2px;">{{ $motto }}</div>
                @endif
            </div>
        </td>
    </tr>
    <tr>
        <td style="padding: 0;">
            <div style="height: 3px; background-color: {{ $accentColor }}; font-size: 0; line-height: 0;">&nbsp;</div>
        </td>
    </tr>

    {{-- SEGMENT 2, instructions, headed by a navy tab. --}}
    <tr>
        <td style="padding: 6px 7px 0; text-align: center;">
            <div style="display: inline-block; background-color: {{ $secondaryColor }}; color: #ffffff; font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; padding: 2px 8px; border-radius: 3px;">Instructions</div>
        </td>
    </tr>
    <tr>
        <td style="padding: 5px 7px 0;">
            <div style="font-size: 5.5px; font-weight: 500; color: #1f2937; line-height: 1.5;">
                @foreach ($instructionLines as $line)
                    <div style="padding-left: 7px; text-indent: -7px; margin-bottom: 2px;"><span style="color: {{ $accentColor }};">&bull;</span>&nbsp; {{ $line }}</div>
                @endforeach
            </div>
        </td>
    </tr>

    {{-- SEGMENT 3, where to send the card back. The address comes from the
    school's own settings on any plan, falling back to its website where one
    exists, see App\Support\SchoolContact. --}}
    @unless ($contact->isEmpty())
        <tr>
            <td style="padding: 5px 7px 0;">
                <div style="height: 1px; background-color: {{ $accentColor }}; font-size: 0; line-height: 0;">&nbsp;</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 7px 0;">
                <table style="width: 100%;">
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-size: 6px; font-weight: bold; color: {{ $secondaryColor }}; text-transform: uppercase;">
                                <img src="{{ $iconPerson }}" style="width: 9px; height: 9px; vertical-align: middle; margin-right: 3px;">If found, please return to:
                            </div>
                            @if ($contact->address)
                                <div style="font-size: 5.5px; color: #1f2937; margin-top: 2px;"><img src="{{ $iconAddress }}" style="width: 9px; height: 9px; vertical-align: middle; margin-right: 3px;">{{ $contact->address }}</div>
                            @endif
                            @if ($contact->phone)
                                <div style="font-size: 5.5px; color: #1f2937; margin-top: 2px;"><img src="{{ $iconPhone }}" style="width: 9px; height: 9px; vertical-align: middle; margin-right: 3px;">{{ $contact->phone }}</div>
                            @endif
                            @if ($contact->email)
                                <div style="font-size: 5.5px; color: #1f2937; margin-top: 2px;"><img src="{{ $iconEmail }}" style="width: 9px; height: 9px; vertical-align: middle; margin-right: 3px;">{{ $contact->email }}</div>
                            @endif
                            @if ($contact->website)
                                <div style="font-size: 5.5px; color: #1f2937; margin-top: 2px;"><img src="{{ $iconWebsite }}" style="width: 9px; height: 9px; vertical-align: middle; margin-right: 3px;">{{ $contact->website }}</div>
                            @endif
                        </td>
                        <td style="width: 48px; vertical-align: top; text-align: right;">
                            <div style="display: inline-block; padding: 2px; border: 1px solid {{ $secondaryColor }}33; border-radius: 3px; background-color: #ffffff; font-size: 0; line-height: 0;">
                                <img src="{{ $qr }}" width="40" height="40" style="width: 40px; height: 40px; display: block;" alt="QR Code">
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @endunless

    {{-- SEGMENT 4, the principal's hand, on the navy bar that closes the
    card. Earlier artwork looked as though the card simply ended after the
    signature; the closer view shows the bar. --}}
    <tr>
        <td style="padding: 5px 0 0;">
            <div style="height: 3px; background-color: {{ $accentColor }}; font-size: 0; line-height: 0;">&nbsp;</div>
        </td>
    </tr>
    <tr>
        <td style="padding: 0;">
            <div style="background-color: {{ $secondaryColor }}; text-align: center; padding: 5px 7px 6px;">
                {{-- 15px reserved either way, exactly as on screen, so the
                     rule and the word "Principal" beneath it print in the same
                     place whether or not a signature is registered. --}}
                <div style="height: 15px; overflow: hidden;">
                    @if ($signature)
                        <img src="{{ $signature['uri'] }}" width="{{ $signature['width'] }}" height="{{ $signature['height'] }}" style="width: {{ $signature['width'] }}px; height: {{ $signature['height'] }}px;">
                    @else
                        <div style="font-size: 9px; line-height: 15px; font-weight: bold; color: {{ \App\Services\GoldSignature::GOLD }}; font-family: 'Brush Script MT', cursive;">{{ $school->principalName() ?: 'Principal' }}</div>
                    @endif
                </div>
                <div style="width: 60px; height: 1px; background-color: rgba(255,255,255,0.65); margin: 2px auto 0; font-size: 0; line-height: 0;">&nbsp;</div>
                <div style="font-size: 6.5px; font-weight: bold; color: #ffffff; margin-top: 2px;">Principal</div>

                {{-- Nothing is drawn when a school has uploaded no stamp: a
                     stamp is a claim of authenticity, and inventing one would
                     be a lie on a document a child carries. --}}
                @if ($stamp)
                    <img src="{{ $stamp['uri'] }}" width="{{ $stamp['width'] }}" height="{{ $stamp['height'] }}" style="width: {{ $stamp['width'] }}px; height: {{ $stamp['height'] }}px; margin-top: 3px;">
                @endif
            </div>
        </td>
    </tr>
</table>
