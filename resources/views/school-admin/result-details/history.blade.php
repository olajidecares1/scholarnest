@php
    $pendingOnPage = $deliveries->getCollection()->contains(fn ($d) => $d->status === \App\Enums\ResultDeliveryStatus::Pending);
    $retryable = $counts['failed'] + $counts['missing_contact'];
@endphp

<x-dashboard-layout page-title="Result Details Sending History" page-subtitle="Every result link, token and admission number sent to parents and guardians, and what happened to it.">
    {{-- While anything on the page is still being sent, look again every few
         seconds so the statuses move on their own. --}}
    @if ($pendingOnPage)
        <div x-data x-init="setTimeout(() => window.location.reload(), 6000)" class="hidden"></div>
    @endif

    <div class="space-y-6" x-data="{ selected: [] }">
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('result-pins.index') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                <i class="fa-solid fa-chevron-left text-[12px]"></i> Exam Tokens
            </a>
            <a href="{{ route('results.index') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                <i class="fa-solid fa-square-poll-vertical text-[12px]"></i> Results
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- WhatsApp that could not go by itself: from the School Admin's own
             phone, one parent after another. Free, from the school's number. --}}
        @if ($whatsAppToSend > 0)
            <div class="flex flex-col gap-3 rounded-[10px] border border-green-200 bg-green-50 p-4 sm:flex-row sm:items-center dark:border-green-900/50 dark:bg-green-900/20">
                <p class="min-w-0 flex-1 text-sm text-gray-800 dark:text-gray-100">
                    <i class="fa-brands fa-whatsapp mr-1 text-[#1ebe5b]" aria-hidden="true"></i>
                    <strong>{{ $whatsAppToSend }} WhatsApp {{ $whatsAppToSend === 1 ? 'message' : 'messages' }}</strong> to send from your phone.
                    WhatsApp opens at each parent's chat with their child's admission number, token and link already typed; press send, come back, and the next parent is ready.
                </p>
                <a href="{{ route('result-pins.details.whatsapp-queue', array_filter(['batch' => $filters['batch']])) }}" class="btn inline-flex shrink-0 items-center justify-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1ebe5b]">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Send to all on WhatsApp
                </a>
            </div>
        @endif

        {{-- The counts, each one a filter. --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ($statuses as $status)
                <a
                    href="{{ request()->fullUrlWithQuery(['status' => $filters['status'] === $status ? null : $status->value, 'page' => null]) }}"
                    class="rounded-[10px] border bg-white p-4 shadow-sm transition hover:shadow dark:bg-gray-800 {{ $filters['status'] === $status ? 'border-primary-400 ring-2 ring-primary-200 dark:ring-primary-900' : 'border-gray-200 dark:border-gray-700' }}"
                >
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $status->badge() }}">{{ $status->label() }}</span>
                    <span class="mt-2 block text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($counts[$status->value]) }}</span>
                </a>
            @endforeach
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <form method="GET" action="{{ route('result-pins.details.history') }}" class="grid gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:grid-cols-4">
                @if ($filters['batch'])
                    <input type="hidden" name="batch" value="{{ $filters['batch'] }}">
                @endif
                <div class="sm:col-span-2">
                    <label for="search" class="field-label mb-1">Student</label>
                    <input id="search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or admission number" class="w-full">
                </div>
                <div>
                    <label for="channel" class="field-label mb-1">Channel</label>
                    <select id="channel" name="channel" class="w-full" onchange="this.form.submit()">
                        <option value="">Email and WhatsApp</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}" @selected($filters['channel'] === $channel)>{{ $channel->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"><i class="fa-solid fa-filter btn-icon" aria-hidden="true"></i> Filter</button>
                    @if ($filters['batch'] || $filters['status'] || $filters['channel'] || $filters['search'])
                        <a href="{{ route('result-pins.details.history') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Show all</a>
                    @endif
                </div>
            </form>

            @if ($filters['batch'])
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Showing one send.</span>
                    @if ($retryable > 0)
                        <form method="POST" action="{{ route('result-pins.details.retry') }}">
                            @csrf
                            <input type="hidden" name="batch" value="{{ $filters['batch'] }}">
                            <button type="submit" class="btn rounded-[8px] bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                                <i class="fa-solid fa-rotate-right btn-icon" aria-hidden="true"></i> Retry {{ $retryable }} failed / missing
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Ticked rows: retry just those. Sent and delivered rows cannot
                 be ticked, so nothing already sent goes twice. --}}
            <div x-show="selected.length" x-cloak class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-5 py-3 dark:border-amber-900/50 dark:bg-amber-900/20">
                <span class="text-sm font-semibold text-amber-800 dark:text-amber-300"><span x-text="selected.length"></span> selected</span>
                <form method="POST" action="{{ route('result-pins.details.retry') }}">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="deliveries[]" :value="id"></template>
                    <button type="submit" class="btn rounded-[8px] bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">
                        <i class="fa-solid fa-rotate-right btn-icon" aria-hidden="true"></i> Retry selected
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="stack-cards w-full min-w-[60rem] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-2.5"><span class="sr-only">Select</span></th>
                            <th class="px-3 py-2.5 font-semibold">Date &amp; Time</th>
                            <th class="px-3 py-2.5 font-semibold">Student</th>
                            <th class="px-3 py-2.5 font-semibold">Result</th>
                            <th class="px-3 py-2.5 font-semibold">Recipient</th>
                            <th class="px-3 py-2.5 font-semibold">Channel</th>
                            <th class="px-3 py-2.5 font-semibold">Status</th>
                            <th class="px-5 py-2.5 text-right font-semibold"><span class="sr-only">Retry</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($deliveries as $delivery)
                            <tr>
                                <td class="px-5 py-2.5">
                                    @if ($delivery->status->isRetryable())
                                        <input type="checkbox" value="{{ $delivery->uuid }}" x-model="selected" aria-label="Select this message">
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-xs text-gray-600 dark:text-gray-300">
                                    {{ ($delivery->sent_at ?? $delivery->created_at)->format('j M Y, g:i a') }}
                                    @if ($delivery->sender)
                                        <small class="block text-gray-400">by {{ $delivery->sender->name }}</small>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $delivery->student?->fullName() ?? 'Removed student' }}</span>
                                    <small class="block font-mono text-xs text-gray-500">{{ $delivery->student?->admission_number }}</small>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($delivery->examination)
                                        {{ $delivery->examination->class_name }}<br>{{ $delivery->examination->term->label() }} &middot; {{ $delivery->examination->session }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($delivery->recipient)
                                        <span class="block font-semibold text-gray-800 dark:text-gray-100">{{ $delivery->recipient_name }}</span>
                                        <span class="text-gray-500">{{ $delivery->channel === \App\Enums\ResultDeliveryChannel::WhatsApp ? '+'.$delivery->recipient : $delivery->recipient }}</span>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                    <i class="{{ $delivery->channel->icon() }} mr-1" aria-hidden="true"></i>{{ $delivery->channel->label() }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $delivery->status->badge() }}">
                                        @if ($delivery->status === \App\Enums\ResultDeliveryStatus::Pending)<i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>@endif{{ $delivery->status->label() }}
                                    </span>
                                    @if ($delivery->error)
                                        <small class="mt-1 block max-w-xs text-[11px] text-gray-500 dark:text-gray-400">{{ $delivery->error }}</small>
                                    @endif
                                    @if ($delivery->attempts > 1)
                                        <small class="block text-[11px] text-gray-400">{{ $delivery->attempts }} attempts</small>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    {{-- WhatsApp by hand: opens this parent's chat with the
                                         message already typed, for when it could not go
                                         automatically (WhatsApp not set up on the server). --}}
                                    @if ($delivery->channel === \App\Enums\ResultDeliveryChannel::WhatsApp && $delivery->recipient && ! in_array($delivery->status, [\App\Enums\ResultDeliveryStatus::Sent, \App\Enums\ResultDeliveryStatus::Delivered], true))
                                        <form method="POST" action="{{ route('result-pins.details.whatsapp', $delivery) }}" target="_blank" class="mb-1 inline-block" x-on:submit="setTimeout(() => window.location.reload(), 1500)">
                                            @csrf
                                            <button type="submit" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-[#25D366] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#1ebe5b]">
                                                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Send on WhatsApp
                                            </button>
                                        </form>
                                    @endif
                                    @if ($delivery->status->isRetryable())
                                        <form method="POST" action="{{ route('result-pins.details.retry') }}">
                                            @csrf
                                            <input type="hidden" name="deliveries[]" value="{{ $delivery->uuid }}">
                                            <button type="submit" class="icon-btn icon-btn--warn" data-tooltip="Retry" aria-label="Retry this message"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Nothing has been sent yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($deliveries->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $deliveries->links() }}</div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
