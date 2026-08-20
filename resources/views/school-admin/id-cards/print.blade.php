<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>ID Cards - {{ $school->name }}</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                @page { margin: 10mm; }
                .no-print { display: none !important; }
            }
            body { background: #f3f4f6; }
        </style>
    </head>
    <body class="p-8">
        <div class="no-print mb-6 flex items-center justify-between">
            <p class="text-sm text-gray-600">{{ $cards->count() }} card(s) ready &mdash; front and back shown below. Use your browser's print dialog to print or "Save as PDF", or download a print-ready PDF directly.</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('id-cards.pdf') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $cards->first()->holder_type->value }}">
                    @foreach ($cards as $card)
                        <input type="hidden" name="records[]" value="{{ $card->holder_uuid }}">
                    @endforeach
                    <button type="submit" class="rounded-[8px] border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download PDF</button>
                </form>
                <button type="button" onclick="window.print()" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Print Now</button>
            </div>
        </div>

        <div class="flex flex-wrap gap-6">
            @foreach ($cards as $card)
                @if ($card->template)
                    @include('school-admin.id-cards._card', ['card' => $card])
                    @include('school-admin.id-cards._card_back', ['card' => $card])
                @endif
            @endforeach
        </div>

        @if ($cards->every(fn ($card) => ! $card->template))
            <p class="text-sm text-amber-700">No template available for this card type.</p>
        @endif

        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    </body>
</html>
