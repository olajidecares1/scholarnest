<x-super-admin-layout page-title="Users" page-subtitle="Manage user accounts and roles across the platform.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.users.index') }}" class="flex flex-wrap items-center gap-3 p-4">
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
                        placeholder="Search by name or email..."
                        class="w-full rounded-[5px] border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                    >
                </div>

                <select name="role" class="rounded-[5px] border-gray-300 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]">
                    <option value="">All Roles</option>
                    <option value="school_admin" @selected(request('role') === 'school_admin')>School Admin</option>
                    <option value="super_admin" @selected(request('role') === 'super_admin')>Super Admin</option>
                </select>

                <button type="submit" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[10px]">
                    Filter
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">User</th>
                            <th class="px-5 py-3 font-semibold">Role</th>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Joined</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $user->role->label() }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $user->school->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    @if ($user->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                                    @else
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">You</span>
                                    @elseif ($user->is_active)
                                        <form method="POST" action="{{ route('super-admin.users.deactivate', $user) }}" onsubmit="return confirm('Deactivate {{ $user->name }}?');">
                                            @csrf
                                            <button type="submit" class="rounded-[5px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 lg:rounded-[10px]">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('super-admin.users.activate', $user) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[5px] border border-green-300 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/20 lg:rounded-[10px]">Activate</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
