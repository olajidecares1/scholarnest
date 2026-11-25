@php
    /*
     * Exam tokens on A4, for printing and handing out.
     *
     * Paged by hand rather than left to dompdf, so every page carries the
     * school's heading and its own "Page 2 of 4", and so the number of rows
     * on a page is fixed: ResultTokenBulkController::ROWS_PER_PAGE (25),
     * over the 15 the brief asks for, in type large enough to read a token
     * off without squinting. Rows alternate in shade so a finger running
     * along a row stays on it.
     */
    $logoPath = \App\Support\PdfImage::fit($school->logoAbsolutePath(), 360);
    $pageCount = $pages->count();
    $serial = 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Exam Tokens · {{ $school->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 11mm 11mm 10mm 11mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #111827; font-size: 10px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .head { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111827; margin-bottom: 3mm; }
        .head td { vertical-align: middle; padding: 0 0 2.5mm 0; }
        .school { font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
        .title { font-size: 11px; font-weight: bold; margin-top: 1mm; }
        .meta { font-size: 8.5px; color: #4b5563; margin-top: 0.8mm; }
        .page-no { text-align: right; font-size: 8.5px; color: #4b5563; white-space: nowrap; }

        table.tokens { width: 100%; border-collapse: collapse; }
        table.tokens th {
            background: #111827; color: #ffffff; font-size: 8.5px; text-transform: uppercase;
            letter-spacing: 0.4px; text-align: left; padding: 2.2mm 2mm;
        }
        table.tokens td { border-bottom: 0.6px solid #d1d5db; padding: 0 2mm; height: 8.8mm; vertical-align: middle; }
        table.tokens tr.alt td { background: #f3f4f6; }
        .sn { width: 9mm; text-align: center; color: #6b7280; }
        .name { font-size: 10.5px; font-weight: bold; line-height: 1.2; }
        .adm { width: 30mm; font-family: 'DejaVu Sans Mono', monospace; font-size: 9.5px; }
        .class { width: 27mm; font-size: 9.5px; }
        .token { width: 52mm; font-family: 'DejaVu Sans Mono', monospace; font-size: 13px; font-weight: bold; letter-spacing: 1.2px; }
        th.sn { text-align: center; color: #ffffff; }

        .foot { margin-top: 3mm; font-size: 8px; color: #6b7280; }
    </style>
</head>
<body>
    @foreach ($pages as $pageIndex => $rows)
        <div class="page">
            <table class="head">
                <tr>
                    @if ($logoPath)
                        <td style="width: 15mm;"><img src="{{ $logoPath }}" style="width: 12mm; height: 12mm;"></td>
                    @endif
                    <td>
                        <div class="school">{{ $school->name }}</div>
                        <div class="title">
                            Examination Tokens &middot; {{ $term->label() }}, {{ $session }}{{ $classLabel ? ' · '.$classLabel : '' }}
                        </div>
                        @if ($resultLinkUrl)
                            <div class="meta">Check results at: {{ $resultLinkUrl }}</div>
                        @endif
                    </td>
                    <td class="page-no">
                        Page {{ $pageIndex + 1 }} of {{ $pageCount }}<br>
                        {{ $total }} student{{ $total === 1 ? '' : 's' }}
                    </td>
                </tr>
            </table>

            <table class="tokens">
                <thead>
                    <tr>
                        {{-- Widths inline: dompdf sizes columns from the first
                             row's own style, not from class rules. --}}
                        <th class="sn" style="width: 10mm;">S/N</th>
                        <th>Student Name</th>
                        <th style="width: 33mm;">Admission No.</th>
                        <th style="width: 25mm;">Class</th>
                        <th style="width: 50mm;">Exam Token</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $pin)
                        @php $serial++; @endphp
                        <tr class="{{ $serial % 2 === 0 ? 'alt' : '' }}">
                            <td class="sn">{{ $serial }}</td>
                            <td class="name">{{ $pin->boundStudent->fullName() }}</td>
                            <td class="adm">{{ $pin->boundStudent->admission_number ?? '—' }}</td>
                            <td class="class">{{ $pin->boundStudent->class_name }}</td>
                            <td class="token">{{ $pin->plainToken() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="foot">
                Each token opens one student's result for {{ $term->label() }}, {{ $session }} only. Keep it private.
                Printed {{ now()->format('j M Y, g:i a') }}.
            </div>
        </div>
    @endforeach
</body>
</html>
