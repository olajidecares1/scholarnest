<x-dashboard-layout page-title="Parents & Guardians" page-subtitle="Manage guardian accounts and their linked children.">
    <div class="space-y-6" x-data="{ open: false, loginUsername: '' }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600">Total Guardians</p>
                    <x-stat-tooltip text="Every parent/guardian account on record for this school." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-green-600">Active Guardians</p>
                    <x-stat-tooltip text="Guardian accounts currently marked active." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($activeCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        @input.debounce.500ms="$event.target.form.submit()"
                        placeholder="Search by name, email or phone..."
                        class="w-64"
                    >
                    <button type="submit" class="btn h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Search</button>
                    @if (request('search'))
                        <a href="{{ route('guardians.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="open = true"
                    class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                    Add Guardian
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Guardian</th>
                            <th class="px-6 py-3 font-semibold">Phone</th>
                            <th class="px-6 py-3 font-semibold">Linked Children</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($guardians as $guardian)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('guardians.show', $guardian) }}" class="flex items-center gap-3">
                                        @if ($guardian->photoUrl())
                                            <img src="{{ $guardian->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                {{ Str::of($guardian->name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endif
                                        <span>
                                            <span class="block font-semibold text-gray-900 hover:text-blue-600 dark:text-white">{{ $guardian->name }}</span>
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $guardian->email }}</small>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $guardian->phone ?? 'N/A' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $guardian->students_count }} {{ Str::plural('child', $guardian->students_count) }}
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $guardian->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $guardian->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('guardians.show', $guardian) }}" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                            Manage
                                        </a>
                                        <form method="POST" action="{{ route('guardians.toggle-active', $guardian) }}">
                                            @csrf @method('POST')
                                            <button type="submit" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                {{ $guardian->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('guardians.destroy', $guardian) }}" onsubmit="return confirm('Remove {{ $guardian->name }}? This unlinks all their children and cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if (request('search'))
                                        No guardians match your search.
                                    @else
                                        No guardians yet. Click "Add Guardian" to add your first parent/guardian account.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($guardians->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $guardians->links() }}
                </div>
            @endif
        </div>

        {{-- Add Guardian modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Guardian</h3>
                <small class="field-hint mt-1">Create the guardian's account here, then link their child/children from the guardian's page.</small>
                <form method="POST" action="{{ route('guardians.store') }}" class="mt-4 space-y-2">
                    @csrf
                    <x-text-field name="name" label="Full Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" required />
                    <x-text-field name="email" type="email" label="Email" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" required />
                    <x-text-field name="phone" label="Phone" icon="M5 4.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1 1 0 01-1 1A15 15 0 015 5.5a1 1 0 011-1z" x-model="loginUsername" helper="Also the username this guardian signs in with." />
                    <x-login-details-fields
                        username-label="Username (Phone Number)"
                        username-model="loginUsername"
                        username-hint="The phone number and password this parent or guardian signs in to the parent portal with."
                        password-hint="Optional. You can set it later from the guardian's page."
                    />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Guardian</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
