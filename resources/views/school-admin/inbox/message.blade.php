{{-- One enquiry, in full.

     The listing shows what each message is about; this is where the message
     itself lives, along with the ways to answer it. Opening this page is what
     marks it read - see InboxController::showMessage. --}}
<x-dashboard-layout page-title="Message" page-subtitle="Sent from your website's contact form.">
    <div class="space-y-6">
        <a href="{{ route('inbox.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-800 dark:text-blue-400">
            <i class="fa-solid fa-arrow-left text-[11px]" aria-hidden="true"></i>
            Back to inbox
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-700">
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold leading-snug text-gray-900 dark:text-white">{{ $message->topic() }}</h2>

                    <p class="field-hint mt-1">
                        From <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $message->name }}</span>
                        &middot; {{ $message->created_at->format('j M Y, g:ia') }}
                    </p>
                </div>

                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $message->isUnread() ? 'bg-blue-100 text-blue-900' : 'bg-gray-100 text-gray-900' }}">
                    {{ $message->isUnread() ? 'Unread' : 'Read' }}
                </span>
            </div>

            {{-- Every word of it, kept exactly as it was typed. --}}
            <p class="mt-5 whitespace-pre-line text-[14px] leading-relaxed text-gray-900 dark:text-gray-100">{{ $message->message }}</p>

            <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 text-[12.5px] font-semibold dark:border-gray-700">
                @if ($message->email)
                    <a href="mailto:{{ $message->email }}" class="flex items-center gap-1.5 text-blue-700 hover:text-blue-800 dark:text-blue-400">
                        <i class="fa-solid fa-envelope text-[12px]" aria-hidden="true"></i>{{ $message->email }}
                    </a>
                @endif

                @if ($message->phone)
                    <a href="tel:{{ $message->phone }}" class="flex items-center gap-1.5 text-blue-700 hover:text-blue-800 dark:text-blue-400">
                        <i class="fa-solid fa-phone text-[12px]" aria-hidden="true"></i>{{ $message->phone }}
                    </a>
                @endif

                @if (! $message->email && ! $message->phone)
                    <span class="text-gray-700 dark:text-gray-300">They left no way to reply.</span>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                @if ($message->read_at)
                    <p class="field-hint">
                        Read by {{ $message->reader?->name ?? 'an administrator' }} on {{ $message->read_at->format('j M Y, g:ia') }}.
                    </p>
                @else
                    <span></span>
                @endif

                <form method="POST" action="{{ route('inbox.read-message', $message) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                        {{ $message->isUnread() ? 'Mark as read' : 'Mark as unread' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
