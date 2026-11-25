@php
    $hidden = function (array $inputs): string {
        $html = '';
        foreach ($inputs as $name => $value) {
            foreach ((array) $value as $item) {
                $html .= '<input type="hidden" name="'.e(is_array($value) ? $name.'[]' : $name).'" value="'.e($item).'">';
            }
        }

        return $html;
    };
    $channelNames = collect($channels)->map->label()->join(' and ');
    $totalMessages = array_sum($summary['messages']);
@endphp

<x-dashboard-layout page-title="Send Result Details" page-subtitle="Check who will receive what before anything is sent.">
    {{-- The confirmation summary. Nothing has been sent yet. --}}
    <div class="space-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Students</small>
                <span class="mt-1 block text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['students'] }}</span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Channels</small>
                <span class="mt-1 block text-sm font-bold text-gray-900 dark:text-white">
                    @foreach ($channels as $channel)
                        <i class="{{ $channel->icon() }} mr-1" aria-hidden="true"></i>{{ $channel->label() }}@if (! $loop->last) &amp; @endif
                    @endforeach
                </span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Messages to send</small>
                <span class="mt-1 block text-2xl font-bold text-gray-900 dark:text-white">{{ $totalMessages }}</span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Missing contact</small>
                <span class="mt-1 block text-2xl font-bold {{ array_sum($summary['missing']) > 0 ? 'text-amber-600' : 'text-gray-900 dark:text-white' }}">{{ array_sum($summary['missing']) }}</span>
            </div>
        </div>

        <div class="rounded-[10px] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-200">
            Each parent/guardian receives only their own child&rsquo;s result link, result token and admission number, at the
            email address and WhatsApp number on that child&rsquo;s record.
        </div>

        @if (in_array(\App\Enums\ResultDeliveryChannel::WhatsApp, $channels, true) && ! $whatsAppReady)
            <div class="rounded-[10px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                WhatsApp sending is not set up on this server yet, so the WhatsApp messages will be recorded as failed.
                Ask your platform administrator to add the WhatsApp Business details, then retry them from the sending history.
            </div>
        @endif

        @if ($notReady->isNotEmpty())
            <div class="rounded-[10px] border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="font-semibold">{{ $notReady->count() }} student(s) have no result for their parents to see yet.</p>
                <small class="mt-1 block text-xs">
                    No marks are recorded{{ auth()->user()->school->resultsComeFromRepository() ? ', or the result has not been pushed to the Result Repository,' : '' }}
                    for: {{ $notReady->join(', ') }}. Their parents would get a token for a result that is empty or not ready.
                    Enter the marks{{ auth()->user()->school->resultsComeFromRepository() ? ' and push them' : '' }} first, or go ahead if you mean to.
                </small>
            </div>
        @endif

        @if ($toIssue->isNotEmpty())
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300">
                {{ $toIssue->count() }} student(s) have no working token for this result (none issued yet, or theirs is used up or expired). A new one is made for each when you press Send, before their message goes out.
            </div>
        @endif

        @if ($unusable->isNotEmpty())
            <div class="rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                <p class="font-semibold">Not included ({{ $unusable->count() }}): their token cannot be used.</p>
                <small class="mt-1 block text-xs">{{ $unusable->map(fn ($u) => $u['name'].' ('.$u['reason'].')')->join(', ') }}</small>
            </div>
        @endif

        {{-- Who gets what. --}}
        <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="stack-cards w-full min-w-[44rem] text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="w-12 px-5 py-2.5 font-semibold">S/N</th>
                            <th class="px-3 py-2.5 font-semibold">Student</th>
                            <th class="px-3 py-2.5 font-semibold">Class</th>
                            @foreach ($channels as $channel)
                                <th class="px-3 py-2.5 font-semibold"><i class="{{ $channel->icon() }} mr-1" aria-hidden="true"></i>{{ $channel->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (collect($summary['rows'])->sortBy(fn ($row) => [$row['student']->class_name, $row['student']->first_name, $row['student']->last_name])->values() as $row)
                            <tr>
                                <td class="px-5 py-2.5 tabular-nums text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</span>
                                    <small class="block font-mono text-xs text-gray-500">{{ $row['student']->admission_number }}</small>
                                </td>
                                <td class="px-3 py-2.5 text-gray-600 dark:text-gray-300">{{ $row['student']->class_name }}</td>
                                @foreach ($channels as $channel)
                                    <td class="px-3 py-2.5 text-xs">
                                        @forelse ($row['contacts'][$channel->value] as $contact)
                                            <span class="block text-gray-700 dark:text-gray-200">{{ $channel === \App\Enums\ResultDeliveryChannel::WhatsApp ? '+'.$contact['address'] : $contact['address'] }}</span>
                                        @empty
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">Missing contact</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                            @if ($row['invalid'] !== [])
                                <tr class="bg-amber-50/60 dark:bg-amber-900/10">
                                    <td></td>
                                    <td colspan="{{ 2 + count($channels) }}" class="px-3 pb-2.5 text-[11px] text-amber-800 dark:text-amber-300">
                                        <i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ implode(' ', $row['invalid']) }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('result-pins.details.send') }}"
            class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 flex flex-wrap items-center gap-3 rounded-[10px] border border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-800/95 lg:bottom-4"
            x-data
            x-on:submit="$el.querySelector('[type=submit]').disabled = true"
        >
            @csrf
            <input type="hidden" name="channel" value="{{ $channelChoice }}">
            {!! $hidden($inputs) !!}

            <a href="{{ $back }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                <i class="fa-solid fa-arrow-left btn-icon" aria-hidden="true"></i> Back
            </a>
            <small class="block min-w-0 flex-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $summary['students'] }} student(s) by {{ $channelNames }}. Students with a missing contact are recorded so you can retry once it is added.
            </small>
            <button type="submit" class="btn rounded-[8px] bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700">
                <i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i> Confirm &amp; Send
            </button>
        </form>
    </div>
</x-dashboard-layout>
