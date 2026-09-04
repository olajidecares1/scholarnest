{{-- One conduct report, in full.

     The photographs and video are shown INLINE - an image renders, a video
     plays with controls - rather than as a list of downloads, because an
     administrator deciding what to do needs to see the thing, and a file that
     has to be downloaded first is a file left in a downloads folder on a
     shared computer.

     Every media URL is the private-disk route that checks the report belongs
     to this school before it opens the file. Nothing here has a public
     address.

     Opening this page does NOT mark the report reviewed. "Reviewed" means an
     administrator decided something, and the form at the bottom is where that
     is recorded - reading is not deciding. --}}
<x-dashboard-layout page-title="Conduct Report" page-subtitle="Sent from your website by a member of the public.">
    <div class="space-y-6" x-data="{ viewer: null }">
        <a href="{{ route('inbox.index', ['tab' => 'reports']) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800 dark:text-blue-400">
            <i class="fa-solid fa-arrow-left text-[11px]" aria-hidden="true"></i>
            Back to inbox
        </a>

        <div class="rounded-[5px] border bg-white p-6 shadow-sm dark:bg-gray-800 lg:rounded-[10px] {{ $report->isNew() ? 'border-amber-300 dark:border-amber-800' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold leading-snug text-gray-900 dark:text-white">{{ $report->topic() }}</h2>

                    <p class="field-hint mt-1">
                        Reported by <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $report->reporter_name }}</span>
                        &middot; {{ $report->created_at->format('j M Y, g:ia') }}
                        @if ($report->location) &middot; {{ $report->location }} @endif
                    </p>
                </div>

                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $report->isNew() ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-900' }}">
                    {{ $report->statusLabel() }}
                </span>
            </div>

            <p class="mt-5 whitespace-pre-line text-[14px] leading-relaxed text-gray-900 dark:text-gray-100">{{ $report->description }}</p>

            @if ($report->attachments->isNotEmpty())
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($report->attachments as $attachment)
                        @php $mediaUrl = route('misconduct-reports.attachment', $attachment); @endphp

                        <div class="overflow-hidden rounded-[8px] border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                            @if ($attachment->isVideo())
                                <video controls preload="metadata" class="w-full bg-black" style="height: 140px;">
                                    <source src="{{ $mediaUrl }}" type="{{ $attachment->mime_type }}">
                                    Your browser cannot play this video.
                                    <a href="{{ $mediaUrl }}">Open it instead.</a>
                                </video>
                            @else
                                <button type="button" x-on:click="viewer = @js($mediaUrl)" class="block w-full" title="View full size">
                                    <img src="{{ $mediaUrl }}" alt="" class="w-full object-cover" style="height: 140px;">
                                </button>
                            @endif

                            <p class="truncate px-2 py-1.5 text-[11px] font-medium text-gray-700 dark:text-gray-300">
                                <i class="fa-solid {{ $attachment->isVideo() ? 'fa-circle-play' : 'fa-image' }} mr-1 text-blue-600" aria-hidden="true"></i>
                                {{ $attachment->readableSize() }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($report->reviewed_at)
                <p class="field-hint mt-5">
                    {{ $report->statusLabel() }} by {{ $report->reviewer?->name ?? 'an administrator' }}
                    on {{ $report->reviewed_at->format('j M Y') }}.
                </p>
            @endif

            <form method="POST" action="{{ route('misconduct-reports.update', $report) }}" class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-700">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="note-{{ $report->uuid }}" class="field-label">What did you do about it?</label>
                        <input id="note-{{ $report->uuid }}" name="review_note" type="text" maxlength="1000" class="mt-1.5 w-full" value="{{ $report->review_note }}" placeholder="Optional note for your own records">
                    </div>

                    <div class="min-w-[150px]">
                        <label for="status-{{ $report->uuid }}" class="field-label">Status</label>
                        <select id="status-{{ $report->uuid }}" name="status" class="mt-1.5 w-full">
                            @foreach ([['new', 'Awaiting review'], ['reviewed', 'Reviewed'], ['dismissed', 'Dismissed']] as [$value, $label])
                                <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700">Save</button>
                </div>
            </form>
        </div>

        {{-- Full-size image viewer. --}}
        <div
            x-show="viewer"
            x-cloak
            x-on:keydown.escape.window="viewer = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 p-6"
            x-on:click="viewer = null"
        >
            <img x-bind:src="viewer" alt="" class="max-h-full max-w-full rounded-[8px]">
        </div>
    </div>
</x-dashboard-layout>
