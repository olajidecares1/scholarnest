<x-dashboard-layout page-title="Report Card Template" page-subtitle="Exactly how your report cards will look when you generate them.">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('results.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                Back to Results
            </a>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm font-bold text-gray-900 dark:text-white">This is the real template</p>
            <p class="field-hint mt-1">
                The page below is rendered by the same template that produces your printed report cards &mdash;
                not a mock-up. The pupil, marks, grades and remarks are sample data; your school&rsquo;s name, logo,
                watermark, colours, address and grade bands are your own.
            </p>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['A4 portrait', 'fa-file-lines'],
                    [$school->logoUrl() ? 'Logo in use' : 'No logo uploaded', 'fa-image'],
                    [$school->logoUrl() ? 'Watermark on' : 'No watermark', 'fa-droplet'],
                    ['All 6 sections', 'fa-list-check'],
                ] as [$label, $icon])
                    <div class="flex items-center gap-2 rounded-[8px] bg-gray-50 px-3 py-2 dark:bg-gray-900/40">
                        <i class="fa-solid {{ $icon }} text-primary-600 dark:text-primary-300"></i>
                        <span class="text-[11px] font-semibold text-gray-700 dark:text-gray-200">{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            @unless ($school->logoUrl())
                <p class="mt-3 rounded-[8px] bg-amber-50 px-3 py-2 text-[11.5px] text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    Upload your school logo under Settings to see it in the letterhead and as the page watermark.
                </p>
            @endunless
        </div>

        {{-- The card itself, at the width an A4 page renders to. Scrolls
             sideways on a narrow screen rather than squashing the layout. --}}
        <div class="overflow-x-auto rounded-[10px] bg-gray-100 p-4 dark:bg-gray-900">
            <div class="mx-auto" style="width: 794px;">
                @include('school-admin.results._report-card', $reportCard)
            </div>
        </div>
    </div>
</x-dashboard-layout>
