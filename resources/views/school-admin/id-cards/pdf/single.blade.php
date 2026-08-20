@php
    $isLandscape = $card->template?->orientation === \App\Enums\IdCardOrientation::Landscape;
    $width = $isLandscape ? '85.6mm' : '53.98mm';
    $height = $isLandscape ? '53.98mm' : '85.6mm';
@endphp
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            @page { margin: 0; size: {{ $width }} {{ $height }}; }
            body { margin: 0; padding: 0; font-family: sans-serif; }
            {{-- No explicit height here: the physical card size is already
            fixed by @page above, and dompdf treats a CSS height on the
            table itself as a hard requirement rather than a target -
            when it's even a fraction taller than the true per-page content
            area (rounding between the mm-based @page size and the table's
            own mm height), dompdf splits the table's rows across a second
            page instead of clipping, no matter how small the content is. --}}
            .pdf-card { width: {{ $width }}; margin: 0; border-collapse: collapse; }
        </style>
    </head>
    <body>{!! view('school-admin.id-cards.pdf._front', ['card' => $card])->render() !!}<div style="page-break-before: always;">{!! view('school-admin.id-cards.pdf._back', ['card' => $card])->render() !!}</div></body>
</html>
