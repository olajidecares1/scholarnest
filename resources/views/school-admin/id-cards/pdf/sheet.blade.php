@php
    $isLandscape = $cards->first()->template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $width = $isLandscape ? '85.6mm' : '53.98mm';
    $height = $isLandscape ? '53.98mm' : '85.6mm';
@endphp
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            @page { margin: 10mm; }
            body { margin: 0; padding: 0; font-family: sans-serif; }
            .pdf-card-wrap { display: inline-block; margin: 0 6px 6px 0; page-break-inside: avoid; }
            {{-- No explicit height - see single.blade.php's .pdf-card comment
            for why dompdf can split a table across pages when its CSS
            height is even a fraction taller than the space actually
            available for it. --}}
            .pdf-card { width: {{ $width }}; border: 1px solid #d1d5db; border-collapse: collapse; }
            .pdf-section-title { font-size: 11px; font-weight: bold; margin: 0 0 6px 0; }
            .pdf-page-break { page-break-before: always; }
        </style>
    </head>
    <body>
        <p class="pdf-section-title">Front Side</p>
        @foreach ($cards as $card)
            @if ($card->template)
                <div class="pdf-card-wrap">
                    @include('school-admin.id-cards.pdf._front', ['card' => $card])
                </div>
            @endif
        @endforeach

        <div class="pdf-page-break">
            <p class="pdf-section-title">Back Side</p>
            @foreach ($cards as $card)
                @if ($card->template)
                    <div class="pdf-card-wrap">
                        @include('school-admin.id-cards.pdf._back', ['card' => $card])
                    </div>
                @endif
            @endforeach
        </div>
    </body>
</html>
