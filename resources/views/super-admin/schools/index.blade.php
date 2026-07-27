<x-super-admin-layout page-title="Schools" page-subtitle="Manage and monitor every school on the platform.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <a
                href="{{ route('super-admin.schools.create') }}"
                class="flex items-center gap-2 rounded-[5px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md lg:rounded-[10px]"
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
                        class="w-full rounded-[5px] border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                    >
                </div>

                <div class="w-full sm:w-48">
                    <x-select-field
                        name="status"
                        :options="['' => 'All Statuses', 'active' => 'Active', 'inactive' => 'Inactive']"
                        :selected="request('status')"
                    />
                </div>

                <button type="submit" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[10px]">
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
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $school->billing_email ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->activeSubscription?->plan?->name ?? 'No plan yet' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->users->count() }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    @if ($school->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                                    @else
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('super-admin.schools.show', $school) }}" class="rounded-[5px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[10px]">View</a>
                                        @if ($school->is_active)
                                            <form method="POST" action="{{ route('super-admin.schools.deactivate', $school) }}" onsubmit="return confirm('Deactivate {{ $school->name }}? Their admins will lose access immediately.');">
                                                @csrf
                                                <button type="submit" class="rounded-[5px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 lg:rounded-[10px]">Deactivate</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}">
                                                @csrf
                                                <button type="submit" class="rounded-[5px] border border-green-300 px-3 py-1.5 text-xs font-semibold text-green-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-green-50 hover:shadow-sm dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/20 lg:rounded-[10px]">Activate</button>
                                            </form>
                                        @endif
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
    </div>
</x-super-admin-layout>
