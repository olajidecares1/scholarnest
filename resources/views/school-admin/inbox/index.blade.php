{{-- Everything the public has sent the school, listed by what it is ABOUT.

     The listing used to print every message and every report in full, one
     under another, with the photographs and the review form attached to each.
     Ten long reports made the page unscannable, and an administrator looking
     for one thing had to read all of it. So this is now a list of topics,
     what it is about, who sent it, when, and its status, and the body lives
     one click away.

     Nothing was dropped to achieve that. Every word is still stored and still
     shown; it is shown on its own page, where there is room for it. --}}
<x-dashboard-layout page-title="Inbox" page-subtitle="Messages and conduct reports sent from your website.">
    <div class="space-y-6" x-data="{ tab: '{{ $tab }}' }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
            @foreach ([['messages', 'Messages', $unreadMessages], ['reports', 'Conduct Reports', $newReports]] as [$key, $label, $count])
                <button
                    type="button"
                    x-on:click="tab = @js($key)"
                    x-bind:class="tab === @js($key) ? 'bg-blue-600 text-white' : 'text-gray-700 dark:text-gray-200'"
                    class="flex items-center gap-2 rounded-[6px] px-4 py-2 text-xs font-bold transition-colors duration-200"
                >
                    {{ $label }}
                    @if ($count > 0)
                        <span class="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ min($count, 99) }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Enquiries --}}
        <div x-show="tab === 'messages'" class="space-y-3">
            @forelse ($messages as $message)
                <div class="flex flex-wrap items-center gap-3 rounded-[5px] border bg-white p-4 shadow-sm dark:bg-gray-800 lg:rounded-[10px] {{ $message->isUnread() ? 'border-blue-300 dark:border-blue-800' : 'border-gray-200 dark:border-gray-700' }}">
                    <div class="min-w-0 flex-1">
                        {{-- The topic, not the message. Anything without a
                             subject gets one derived from its opening sentence
                             rather than an empty cell. --}}
                        <a href="{{ route('inbox.message', $message) }}" class="block truncate text-sm font-bold text-gray-900 hover:text-blue-700 dark:text-white dark:hover:text-blue-400">
                            {{ $message->topic() }}
                            @if ($message->isUnread())
                                <span class="ml-1.5 rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white">New</span>
                            @endif
                        </a>

                        <small class="field-hint mt-0.5 truncate">
                            {{ $message->name }} &middot; {{ $message->created_at->format('j M Y, g:ia') }}
                        </small>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <form method="POST" action="{{ route('inbox.read-message', $message) }}">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                                {{ $message->isUnread() ? 'Mark read' : 'Mark unread' }}
                            </button>
                        </form>

                        <a href="{{ route('inbox.message', $message) }}" class="btn rounded-[8px] bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white transition-colors duration-200 hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
            @empty
                <p class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    No messages yet. Anyone can write to you from the Contact section of your website.
                </p>
            @endforelse

            {{ $messages->links() }}
        </div>

        {{-- Conduct reports --}}
        <div x-show="tab === 'reports'" x-cloak class="space-y-3">
            @forelse ($reports as $report)
                <div class="flex flex-wrap items-center gap-3 rounded-[5px] border bg-white p-4 shadow-sm dark:bg-gray-800 lg:rounded-[10px] {{ $report->isNew() ? 'border-amber-300 dark:border-amber-800' : 'border-gray-200 dark:border-gray-700' }}">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('inbox.report', $report) }}" class="block truncate text-sm font-bold text-gray-900 hover:text-blue-700 dark:text-white dark:hover:text-blue-400">
                            {{ $report->topic() }}
                        </a>

                        <small class="field-hint mt-0.5 truncate">
                            {{ $report->reporter_name }} &middot; {{ $report->created_at->format('j M Y, g:ia') }}
                            @if ($report->location) &middot; {{ $report->location }} @endif
                            @if ($report->attachments_count ?? $report->attachments->count())
                                &middot; {{ $report->attachments->count() }} attached
                            @endif
                        </small>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $report->isNew() ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-900' }}">
                            {{ $report->statusLabel() }}
                        </span>

                        <a href="{{ route('inbox.report', $report) }}" class="btn rounded-[8px] bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white transition-colors duration-200 hover:bg-blue-700">
                            View
                        </a>
                    </div>
                </div>
            @empty
                <p class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    No conduct reports yet.
                </p>
            @endforelse

            {{ $reports->links() }}
        </div>
    </div>
</x-dashboard-layout>
