@php
    $school = $card->school;
    $template = $card->template;
    // Defined once, in App\Support\IdCardDesign - see _card.blade.php.
    ['primary' => $primaryColor, 'secondary' => $secondaryColor, 'accent' => $accentColor]
        = \App\Support\IdCardDesign::colors($template);

    $isLandscape = $template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $cardWidth = $isLandscape ? '85.6mm' : '53.98mm';
    $cardHeight = $isLandscape ? '53.98mm' : '85.6mm';
    // Set in Settings on every plan, not only by schools with a public
    // website - see App\Support\SchoolMotto.
    $motto = \App\Support\SchoolMotto::for($school)->tagline;
    $qr = app(\App\Services\CodeImageGenerator::class)->qrCodeDataUri($card->verificationUrl(), 160);

    // The school's own address, on every plan - see App\Support\SchoolContact.
    $contact = \App\Support\SchoolContact::for($school);

    $instructionLines = collect(explode("\n", $template?->instructions ?: \App\Support\IdCardDesign::instructions($school)))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();

    // Each contact line is a red disc with a white glyph, as on the reference.
    $discIcon = fn (string $path, string $viewBox = '0 0 512 512') => '<svg viewBox="'.$viewBox.'" style="width:5px;height:5px;display:block;"><path fill="#ffffff" d="'.$path.'"/></svg>';

    $icons = [
        'address' => $discIcon('M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512'),
        'phone' => $discIcon('M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z'),
        'email' => $discIcon('M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z'),
        'website' => $discIcon('M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zm28.8-64H503.9c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64zm112.6-32H376.7c-10-63.9-29.8-117.4-55.3-151.6c78.3 20.7 142 77.5 171.9 151.6zm-149.1 0H167.7c6.1-36.4 15.5-68.6 27-94.7c10.5-23.6 22.2-40.7 33.5-51.5C239.4 3.2 248.7 0 256 0s16.6 3.2 27.8 13.8c11.3 10.8 23 27.9 33.5 51.5c11.6 26 20.9 58.2 27 94.7zm-209 0H18.6C48.6 85.9 112.2 29.1 190.6 8.4C165.1 42.6 145.3 96.1 135.3 160zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM194.7 446.6c-11.6-26-20.9-58.2-27-94.6H344.3c-6.1 36.4-15.5 68.6-27 94.6c-10.5 23.6-22.2 40.7-33.5 51.5C272.6 508.8 263.3 512 256 512s-16.6-3.2-27.8-13.8c-11.3-10.8-23-27.9-33.5-51.5zM135.3 352c10 63.9 29.8 117.4 55.3 151.6C112.2 482.9 48.6 426.1 18.6 352H135.3zm358.1 0c-30 74.1-93.6 130.9-171.9 151.6c25.5-34.2 45.2-87.7 55.3-151.6H493.4z'),
    ];

    $disc = fn (string $icon) => '<span style="display:inline-flex;align-items:center;justify-content:center;width:9px;height:9px;border-radius:9999px;background-color:'.e($accentColor).';flex-shrink:0;">'.$icon.'</span>';
@endphp

<div
    class="id-card flex h-full flex-col overflow-hidden text-gray-900"
    style="width: {{ $cardWidth }}; height: {{ $cardHeight }}; background-color: #f7f7f8; border: 1.5px solid {{ $secondaryColor }}; border-radius: 5px; font-family: 'Inter', sans-serif;"
>
    {{-- SEGMENT 1 - the navy crown: lanyard slot, crest, school name and
         tagline, closed off with the same red stripe the front carries. --}}
    <div class="shrink-0 text-center text-white" style="background-color: {{ $secondaryColor }}; padding: 6px 7px 8px; border-top-left-radius: 3.5px; border-top-right-radius: 3.5px;">
        <div class="flex justify-center" style="padding-bottom: 5px;">
            <span style="display: block; width: 64px; height: 11px; border-radius: 6px; background-color: #ffffff;"></span>
        </div>

        @if ($school->logoUrl())
            <img src="{{ $school->logoUrl() }}" class="mx-auto shrink-0 object-contain" style="width: 52px; height: 52px;">
        @endif

        <p class="line-clamp-2 text-[11px] font-extrabold uppercase leading-[1.05]" style="margin-top: 4px; font-family: Georgia, 'Times New Roman', serif;">{{ $school->name }}</p>

        @if ($motto)
            <p class="line-clamp-2 text-[5.5px] font-semibold leading-tight text-white/90" style="margin-top: 2px;">{{ $motto }}</p>
        @endif
    </div>

    <div class="shrink-0" style="height: 3px; background-color: {{ $accentColor }};"></div>

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden" style="padding: 6px 7px 0;">
        {{-- SEGMENT 2 - instructions, headed by a navy tab with a rule running
             out to each margin. --}}
        <div class="flex shrink-0 items-center" style="gap: 4px;">
            <span class="flex-1" style="height: 1px; background-color: {{ $secondaryColor }}55;"></span>
            <span class="text-[6.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $secondaryColor }}; padding: 2px 8px; border-radius: 3px;">Instructions</span>
            <span class="flex-1" style="height: 1px; background-color: {{ $secondaryColor }}55;"></span>
        </div>

        <ul class="shrink-0 text-[5.5px] font-medium leading-snug" style="color: #1f2937; margin-top: 5px;">
            @foreach ($instructionLines as $line)
                <li class="flex items-start" style="gap: 4px; margin-bottom: 2.5px;">
                    <span class="shrink-0" style="width: 3px; height: 3px; border-radius: 9999px; background-color: {{ $accentColor }}; margin-top: 2.5px;"></span>
                    <span class="min-w-0">{{ $line }}</span>
                </li>
            @endforeach
        </ul>

        {{-- SEGMENT 3 - where to send the card back. The address comes from the
             school's own settings on any plan, falling back to its website
             where one exists - see App\Support\SchoolContact. --}}
        @unless ($contact->isEmpty())
            <div class="shrink-0" style="height: 1px; background-color: {{ $accentColor }}; margin: 5px 0;"></div>

            <div class="flex min-h-0 items-start" style="gap: 5px;">
                <div class="min-w-0 flex-1">
                    <p class="flex items-center text-[6px] font-bold uppercase" style="color: {{ $secondaryColor }}; gap: 4px;">
                        {!! $disc($discIcon('M406.5 399.6C387.4 352.9 341.5 320 288 320H224c-53.5 0-99.4 32.9-118.5 79.6C69.9 362.2 48 311.7 48 256C48 141.1 141.1 48 256 48s208 93.1 208 208c0 55.7-21.9 106.2-57.5 143.6zM256 288a72 72 0 1 0 0-144 72 72 0 1 0 0 144z')) !!}
                        If found, please return to:
                    </p>

                    <div style="margin-top: 3px;">
                        @foreach ([
                            ['address', $contact->address],
                            ['phone', $contact->phone],
                            ['email', $contact->email],
                            ['website', $contact->website],
                        ] as [$key, $value])
                            @continue (blank($value))
                            <p class="flex items-start text-[5.5px] leading-snug" style="color: #1f2937; gap: 4px; margin-bottom: 2px;">
                                {!! $disc($icons[$key]) !!}
                                <span class="min-w-0">{{ $value }}</span>
                            </p>
                        @endforeach
                    </div>
                </div>

                <span class="shrink-0" style="padding: 2px; border: 1px solid {{ $secondaryColor }}33; border-radius: 3px; background-color: #ffffff;">
                    <img src="{{ $qr }}" style="width: 40px; height: 40px; display: block;" alt="QR Code">
                </span>
            </div>
        @endunless
    </div>

    {{-- SEGMENT 4 - the principal's hand, on the navy bar that closes the
         card. Earlier artwork looked as though the card simply ended after the
         signature; the closer view shows the bar. --}}
    <div class="shrink-0" style="height: 3px; background-color: {{ $accentColor }};"></div>

    <div class="shrink-0 text-center text-white" style="background-color: {{ $secondaryColor }}; padding: 5px 7px 6px;">
        {{-- School Admin is the Principal, so this is their own registered
             signature, in gold, resolved from THIS school's admin account.

             THE SPACE IS RESERVED WHETHER OR NOT ANYTHING FILLS IT. A fixed
             15px box, so the rule beneath it and the word "Principal" beneath
             that are in exactly the same place on every card this school
             issues - a school that registers a signature later does not get
             cards laid out differently from the ones it printed last term.

             Where none is registered, the printed name stands in at the same
             height. Never anybody else's mark. --}}
        @php($principalSignature = \App\Support\PrincipalSignature::for($school))

        <div class="flex items-end justify-center" style="height: 15px;">
            @if ($principalSignature?->dataUri())
                <img src="{{ $principalSignature->dataUri() }}" alt="" class="mx-auto object-contain" style="max-height: 15px; object-fit: contain;">
            @else
                <p class="text-[9px] font-bold leading-none" style="color: {{ \App\Services\GoldSignature::GOLD }}; font-family: 'Segoe Script', 'Brush Script MT', cursive;">{{ $school->principalName() ?: 'Principal' }}</p>
            @endif
        </div>

        <span class="mx-auto block" style="width: 60px; height: 0.5px; background-color: rgba(255,255,255,0.65); margin-top: 2px;"></span>

        <p class="text-[6.5px] font-bold leading-tight" style="margin-top: 2px;">Principal</p>

        {{-- The school's stamp, beside the signature the way it sits on paper.
             Shown only when one has been uploaded, so a card from a school
             without one is simply the card without it - nothing is drawn in
             its place, because a stamp is a claim of authenticity and an
             invented one would be a lie on a document a child carries. --}}
        @if ($school->hasStamp())
            <img
                src="{{ $school->stampDataUri() }}"
                alt="{{ $school->name }} official stamp"
                class="mx-auto object-contain"
                style="max-height: 26px; max-width: 60px; object-fit: contain; margin-top: 3px; opacity: 0.95;"
            >
        @endif
    </div>
</div>
