<x-super-admin-layout page-title="Schools" page-subtitle="Manage and monitor every school on the platform.">
    <div class="space-y-6" x-data="{ deleting: null, typed: '' }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <a
                href="{{ route('super-admin.schools.create') }}"
                class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Add School
            </a>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.schools.index') }}" class="flex flex-wrap items-center gap-3 p-4">
                <div class="relative flex-1 min-w-[200px]">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.75" />
                            <path d="M20 20l-3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search schools..."
                        class="w-full pl-9"
                    >
                </div>

                <div class="w-full sm:w-48">
                    <x-select-field
                        name="status"
                        :options="['' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive']"
                        :selected="request('status')"
                    />
                </div>

                <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Plan</th>
                            <th class="px-5 py-3 font-semibold">Admins</th>
                            <th class="px-5 py-3 font-semibold">Joined</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($schools as $school)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('super-admin.schools.show', $school) }}" class="font-semibold text-gray-900 transition-colors duration-200 hover:text-primary-500 dark:text-white dark:hover:text-primary-400">{{ $school->name }}</a>
                                    <p class="field-hint">{{ $school->billing_email ?? 'N/A' }}</p>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->subscriptionForDisplay()?->plan?->name ?? 'No plan yet' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->users->count() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    {{-- The authoritative state, not `is_active`.
                                         `is_active` only means "not suspended" and defaults to
                                         true, so reading it here labelled every school "Active"
                                         the moment it registered, before any payment had been
                                         reviewed and while it could reach nothing. Suspension is
                                         reported separately below, because a suspended school and
                                         an unapproved one are different problems. --}}
                                    @if (! $school->is_active)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Suspended</span>
                                    @elseif ($school->hasActiveSubscription())
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                                    @elseif ($subscription = $school->subscriptionForDisplay())
                                        @php($badge = match ($subscription->status) {
                                            \App\Enums\SubscriptionStatus::Rejected => ['Rejected', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
                                            \App\Enums\SubscriptionStatus::Expired => ['Expired', 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
                                            default => ['Pending activation', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
                                        })
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">No plan yet</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('super-admin.schools.show', $school) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">View</a>
                                        @if ($school->is_active)
                                            <form method="POST" action="{{ route('super-admin.schools.deactivate', $school) }}" onsubmit="return confirm('Suspend {{ $school->name }}? Their admins will lose access immediately. This does not change their subscription.');">
                                                @csrf
                                                <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Suspend</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}">
                                                @csrf
                                                <button type="submit" class="rounded-[8px] border border-green-300 px-3 py-1.5 text-xs font-semibold text-green-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-green-50 hover:shadow-sm dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/20">Restore</button>
                                            </form>
                                        @endif

                                        {{-- Deliberately last, visually quietest, and behind a typed
                                             confirmation. Suspend above is the reversible action and
                                             should be the one that is easy to reach; this one erases the
                                             school's students, staff, results and invoices for good. --}}
                                        <button
                                            type="button"
                                            @click="deleting = { name: @js($school->name), url: @js(route('super-admin.schools.destroy', $school)) }; typed = ''"
                                            class="rounded-[8px] px-3 py-1.5 text-xs font-semibold text-gray-400 transition-all duration-200 hover:bg-red-50 hover:text-red-700 dark:text-gray-500 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No schools found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($schools->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $schools->links() }}
                </div>
            @endif
        </div>

        {{-- Delete confirmation.
             The name has to be typed exactly. The button below stays disabled
             until it matches, so there is no way to delete the wrong school by
             muscle memory on a row of near-identical Delete buttons. --}}
        <div
            x-cloak
            x-show="deleting"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/70 p-4 backdrop-blur-sm sm:items-center"
            @click.self="deleting = null"
            @keydown.escape.window="deleting = null"
            role="dialog"
            aria-modal="true"
        >
            <div class="my-auto w-full max-w-md rounded-[10px] bg-white p-6 shadow-2xl dark:bg-gray-800">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    Delete <span x-text="deleting?.name"></span>?
                </h2>

                <div class="mt-4 rounded-[8px] bg-red-50 p-3 text-xs text-red-800 dark:bg-red-900/20 dark:text-red-300">
                    <p class="font-semibold">This cannot be undone.</p>
                    <p class="mt-1">
                        Every record belonging to this school goes with it &mdash; students, staff, guardians,
                        attendance, examinations, results, result tokens, invoices, its website and its
                        support tickets.
                    </p>
                    <p class="mt-2">
                        If you only want to stop the school using AkademicNest, close this and choose
                        <span class="font-semibold">Suspend</span> instead. That is reversible.
                    </p>
                </div>

                {{-- The submitting flag is raised on the FORM's submit event, not on
                     the button's click. Disabling a submit button from inside its own
                     click handler cancels the submission the click was about to
                     trigger: the form never posts, and the button sits on
                     "Deleting..." for ever. By the time submit fires, the browser is
                     already committed to sending it. --}}
                <form method="POST" :action="deleting?.url" class="mt-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    @method('DELETE')

                    <label for="confirm_name" class="field-label">
                        Type <span class="font-mono font-bold" x-text="deleting?.name"></span> to confirm
                    </label>
                    <input
                        id="confirm_name"
                        name="confirm_name"
                        type="text"
                        x-model="typed"
                        autocomplete="off"
                        class="mt-1 w-full"
                    >

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="deleting = null"
                            class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>

                        {{-- Disabled until the name matches, and again the moment it
                             is submitted, so a slow response cannot be turned into
                             two delete requests. --}}
                        <button
                            type="submit"
                            :disabled="typed.trim() !== deleting?.name || submitting"
                            class="rounded-[8px] bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-red-600/30 transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span x-show="! submitting">Delete permanently</span>
                            <span x-show="submitting" x-cloak>Deleting&hellip;</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-super-admin-layout>
