<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>ID Cards | {{ $school->name }}</title>
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
            <div>
                <p class="text-base font-bold text-gray-900">Preview: {{ $cards->count() }} ID card{{ $cards->count() === 1 ? '' : 's' }}</p>
                <small class="block text-sm text-gray-600">Front and back of each card below. Check them, then print, or download a print-ready PDF.</small>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('id-cards.pdf') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $cards->first()->holder_type->value }}">
                    <input type="hidden" name="template" value="{{ $template ?? '' }}">
                    @foreach ($cards as $card)
                        <input type="hidden" name="records[]" value="{{ $card->holder_uuid }}">
                    @endforeach
                    <button type="submit" class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download PDF</button>
                </form>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Now</button>
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

        @if ($autoprint ?? false)
            <script>
                window.addEventListener('load', function () {
                    window.print();
                });
            </script>
        @endif
    </body>
</html>
