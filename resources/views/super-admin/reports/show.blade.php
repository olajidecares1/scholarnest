@php
    $statusColors = ['new' => 'bg-amber-100 text-amber-700', 'reviewing' => 'bg-blue-100 text-blue-700', 'resolved' => 'bg-green-100 text-green-700'];
@endphp

<x-super-admin-layout :page-title="$report->reference" page-subtitle="Report review.">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if (session('status'))
                <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $report->reference }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Submitted {{ $report->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $statusColors[$report->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $report->status->label() }}
                    </span>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $report->description }}</p>

                @if ($report->media_path)
                    <a
                        href="{{ route('super-admin.reports.media', $report) }}"
                        class="mt-4 inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3M12 4v12m0-12l-4 4m4-4l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Download Attached Media
                    </a>
                @endif
            </div>

            @if ($report->resolution_notes)
                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Resolution Notes</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $report->resolution_notes }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Report Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">School</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $report->school?->name ?? 'Not specified' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Reporter</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $report->reporter_name ?? 'Anonymous' }}</dd>
                    </div>
                    @if ($report->reporter_email)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $report->reporter_email }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Update Status</h3>
                <form method="POST" action="{{ route('super-admin.reports.update', $report) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <x-select-field
                        id="status"
                        name="status"
                        label="Status"
                        icon="M7 13.5l2.2 2.2L14 11"
                        helper="Where this report stands in your review process."
                        :options="collect(\App\Enums\ReportStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                        :selected="$report->status->value"
                    />

                    <x-textarea-field
                        id="resolution_notes"
                        name="resolution_notes"
                        label="Resolution Notes"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        helper="Internal notes only — not shared with the reporter."
                        rows="4"
                        placeholder="Internal notes about how this was handled..."
                        :value="old('resolution_notes', $report->resolution_notes)"
                    />

                    <button
                        type="submit"
                        class="w-full rounded-[8px] bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-800 hover:shadow-md dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        Update Report
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-super-admin-layout>
