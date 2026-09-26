@php
    $statusColors = ['open' => 'bg-amber-100 text-amber-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'resolved' => 'bg-green-100 text-green-700', 'closed' => 'bg-gray-200 text-gray-600'];
@endphp

<x-super-admin-layout :page-title="$ticket->subject" :page-subtitle="'Ticket from '.$ticket->school->name">
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
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $ticket->subject }}</h2>
                        <small class="field-hint mt-1">Opened by {{ $ticket->openedBy->name }} &middot; {{ $ticket->created_at->diffForHumans() }}</small>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $statusColors[$ticket->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $ticket->status->label() }}
                    </span>
                </div>
                <p class="mt-4 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $ticket->message }}</p>
            </div>

            @foreach ($ticket->replies as $reply)
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $reply->user->name }}</p>
                        <small class="block text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</small>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $reply->message }}</p>
                </div>
            @endforeach

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <form method="POST" action="{{ route('super-admin.support-tickets.reply', $ticket) }}" class="space-y-2">
                    @csrf
                    <x-textarea-field
                        id="message"
                        name="message"
                        label="Reply to School"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        helper="The school will be notified by email and in-app."
                        rows="4"
                        required
                        placeholder="Type your reply..."
                        :value="old('message')"
                    />
                    <button
                        type="submit"
                        class="btn flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
                    >
                        Send Reply
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Ticket Details</h3>
                <form method="POST" action="{{ route('super-admin.support-tickets.update', $ticket) }}" class="mt-4 space-y-2">
                    @csrf
                    @method('PUT')

                    <x-select-field
                        id="status"
                        name="status"
                        label="Status"
                        icon="M7 13.5l2.2 2.2L14 11"
                        helper="Where this ticket stands right now."
                        :options="collect(\App\Enums\TicketStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                        :selected="$ticket->status->value"
                    />

                    <x-select-field
                        id="assigned_to"
                        name="assigned_to"
                        label="Assigned To"
                        icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                        helper="Who on the team is handling this ticket."
                        :options="collect(['' => 'Unassigned'])->union($superAdmins->pluck('name', 'id'))"
                        :selected="(string) $ticket->assigned_to"
                    />

                    <button
                        type="submit"
                        class="btn w-full rounded-[8px] bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-800 hover:shadow-md dark:bg-gray-700 dark:hover:bg-gray-600"
                    >
                        Update Ticket
                    </button>
                </form>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">School</h3>
                <a href="{{ route('super-admin.schools.show', $ticket->school) }}" class="mt-2 block text-sm font-semibold text-primary-500 hover:text-primary-600">
                    {{ $ticket->school->name }}
                </a>
                <small class="field-hint mt-1">Priority: {{ $ticket->priority->label() }}</small>
            </div>
        </div>
    </div>
</x-super-admin-layout>
