{{--
    A token-delivered result.

    The report card itself is the shared partial every other portal renders -
    School Admin, Teacher, Student and Guardian all include this same file from
    the same ReportCardData payload. A parent reaching a result through a token
    therefore sees exactly the document the school sees, attendance, position,
    grades, remarks and all, rather than a thinner "public" version of it.

    Deliberately standalone rather than wrapped in a portal layout: this page is
    reached with no account at all, and Basic schools have no portal to wrap it
    in.
--}}
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- A result is a child's record. Keep it out of search indexes and out
             of the referrer sent to whatever the parent clicks next. --}}
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">

        <title>Result - {{ $student->fullName() }} - {{ $school->name }}</title>

        @vite(['resources/css/app.css'])

        <style>
            @media print {
                @page { size: A4; margin: 10mm; }
                .no-print { display: none !important; }
                body { background: #ffffff; }
            }
            body { background: #f3f4f6; }

            /* Below the "sm" breakpoint the sheet keeps a readable width and
               the container scrolls, instead of the sheet shrinking to the
               phone. 640px is the width its 7-9px type was designed against. */
            @media (max-width: 639px) {
                .report-card-sheet { min-width: 640px; }
            }

            /* On paper there is no container to scroll, so drop the minimum
               and let the sheet size itself to the page. */
            @media print {
                .report-card-scroll { overflow: visible !important; margin: 0 !important; padding: 0 !important; }
                .report-card-sheet { min-width: 0 !important; }
            }
        </style>
    </head>
    <body class="p-4 sm:p-8">
        @php($pin = $usage->pin)

        {{-- This school, on screen as well as on the card itself. The
             report card below already carries the badge; the bar above it
             said nothing about whose result this was. --}}
        <div class="no-print mx-auto mb-4 flex max-w-4xl items-center gap-2.5">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="h-9 w-9 shrink-0 rounded-[8px] object-contain">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-blue-100 text-sm font-extrabold text-blue-700">{{ \Illuminate\Support\Str::of($school->name)->substr(0, 1)->upper() }}</span>
            @endif
            <span class="truncate text-base font-bold text-gray-900">{{ $school->name }}</span>
        </div>

        <div class="no-print mx-auto mb-5 flex max-w-4xl flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-gray-600">
                This token has been used {{ $pin->uses_count }} of {{ $pin->max_uses }} times &middot;
                {{ $pin->remainingUses() }} view{{ $pin->remainingUses() === 1 ? '' : 's' }} remaining.
            </p>

            {{-- Both actions live only on this page, which is only reachable
                 after a token has been verified. The download address runs the
                 same checks again rather than trusting the referrer. --}}
            {{-- Inline @php, matching line 53 - a block @php after an inline one
                 is not compiled correctly in this file. --}}
            @php($downloadUrl = request()->routeIs('school-result.*')
                ? route('school-result.download', ['school' => $school->result_link_slug, 'usage' => $usage])
                : route('check-result.download', ['school' => $school, 'usage' => $usage]))

            <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ $downloadUrl }}"
                class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Download Result
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition hover:bg-blue-700"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 9V3h12v6M6 18H4a1 1 0 01-1-1v-5a1 1 0 011-1h16a1 1 0 011 1v5a1 1 0 01-1 1h-2M6 14h12v7H6v-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Print Result
            </button>
            </div>
        </div>

        {{-- The report card is a fixed A4-proportioned sheet whose type runs as
             small as 7px. Squeezed into a 375px phone it is both unreadable and
             clipped, and this is the one place a report card is routinely opened
             on a phone - a parent following a link, with no dashboard and no
             desktop involved anywhere in the flow.

             So on small screens it is given a legible minimum width and allowed
             to scroll sideways inside its own container, rather than being
             scaled down to fit. The page body itself never scrolls
             horizontally. Printing is unaffected: the wrapper releases the
             minimum width and the sheet lays out at its natural A4 size. --}}
        <div class="report-card-scroll -mx-4 overflow-x-auto px-4 sm:mx-0 sm:overflow-visible sm:px-0">
            <div class="report-card-sheet mx-auto">
                @include('school-admin.results._report-card')
            </div>
        </div>
    </body>
</html>
