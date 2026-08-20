<x-super-admin-layout page-title="Result PINs" page-subtitle="Generate and manage result-checking PINs for Basic-plan schools.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.result-pins.index') }}" class="flex flex-wrap items-center gap-3 p-4">
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
                        placeholder="Search Basic-plan schools..."
                        class="w-full rounded-[8px] border-gray-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>
                <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Search
                </button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">School</th>
                            <th class="px-5 py-3 font-semibold">Total PINs</th>
                            <th class="px-5 py-3 font-semibold">Unassigned</th>
                            <th class="px-5 py-3 font-semibold">Active</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($schools as $school)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $school->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->result_checking_pins_count }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->unassigned_pins_count }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $school->active_pins_count }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('super-admin.result-pins.show', $school) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage PINs</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No Basic-plan schools found.</td>
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
