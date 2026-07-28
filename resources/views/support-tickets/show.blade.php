@php
    $statusColors = ['open' => 'bg-amber-100 text-amber-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'resolved' => 'bg-green-100 text-green-700', 'closed' => 'bg-gray-200 text-gray-600'];
@endphp

<x-dashboard-layout :page-title="$ticket->subject" page-subtitle="Support ticket thread.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ $ticket->subject }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Opened {{ $ticket->created_at->diffForHumans() }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $ticket->status->label() }}
                </span>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $ticket->message }}</p>
        </div>

        <div class="space-y-4">
            @foreach ($ticket->replies as $reply)
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900">{{ $reply->user->name }}</p>
                        <p class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</p>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $reply->message }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <form method="POST" action="{{ route('support-tickets.reply', $ticket) }}" class="space-y-3">
                @csrf
                <x-textarea-field
                    id="message"
                    name="message"
                    label="Add a Reply"
                    icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                    helper="The school support team will be notified of your reply."
                    rows="4"
                    required
                    placeholder="Type your reply..."
                    :value="old('message')"
                />
                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-[2px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
                >
                    Send Reply
                </button>
            </form>
        </div>
    </div>
</x-dashboard-layout>
