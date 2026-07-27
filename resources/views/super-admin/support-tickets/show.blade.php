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
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Opened by {{ $ticket->openedBy->name }} &middot; {{ $ticket->created_at->diffForHumans() }}</p>
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
                        <p class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</p>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $reply->message }}</p>
                </div>
            @endforeach

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <form method="POST" action="{{ route('super-admin.support-tickets.reply', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-input-label for="message" value="Reply to School" />
                    <textarea
                        id="message"
                        name="message"
                        rows="4"
                        required
                        placeholder="Type your reply..."
                        class="w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 placeholder:text-gray-400 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                    >{{ old('message') }}</textarea>
                    <x-input-error :messages="$errors->get('message')" class="mt-2" />
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg lg:rounded-[10px]"
                    >
                        Send Reply
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Ticket Details</h3>
                <form method="POST" action="{{ route('super-admin.support-tickets.update', $ticket) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select
                            id="status"
                            name="status"
                            class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-2.5 pl-3 pr-3 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                        >
                            @foreach (\App\Enums\TicketStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="assigned_to" value="Assigned To" />
                        <select
                            id="assigned_to"
                            name="assigned_to"
                            class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-2.5 pl-3 pr-3 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                        >
                            <option value="">Unassigned</option>
                            @foreach ($superAdmins as $admin)
                                <option value="{{ $admin->id }}" @selected($ticket->assigned_to === $admin->id)>{{ $admin->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-[5px] bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-800 hover:shadow-md dark:bg-gray-700 dark:hover:bg-gray-600 lg:rounded-[10px]"
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
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Priority: {{ $ticket->priority->label() }}</p>
            </div>
        </div>
    </div>
</x-super-admin-layout>
