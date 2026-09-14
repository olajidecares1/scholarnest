<x-super-admin-layout
    page-title="Result Tokens"
    page-subtitle="Oversight of result tokens across every school. Schools issue their own; the platform watches and can revoke."
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        {{-- Platform totals, so a spike in failures is visible without opening
             each school in turn. --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
            @foreach ([
                ['Tokens', $stats['tokens'], 'text-gray-600'],
                ['Active', $stats['active'], 'text-green-600'],
                ['Suspended', $stats['suspended'], 'text-amber-600'],
                ['Revoked', $stats['revoked'], 'text-red-600'],
                ['Views today', $stats['views_today'], 'text-blue-600'],
                ['Failures today', $stats['failures_today'], 'text-red-600'],
            ] as [$label, $value, $colour])
                <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-xs font-medium {{ $colour }}">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        {{-- Global token settings. Defaults only: they shape what schools mint
             from here on and never touch a token already in a parent's hands. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Global token settings</h2>
                <p class="field-hint mt-0.5">
                    The defaults every school's newly issued tokens inherit. Changing these does not affect tokens already issued.
                </p>
            </div>

            <form method="POST" action="{{ route('super-admin.result-pins.settings') }}" class="flex flex-wrap items-end gap-4 p-5">
                @csrf
                @method('PUT')

                <div class="min-w-[180px] flex-1">
                    <label for="result_token_max_uses" class="field-label">Views allowed per token</label>
                    <input
                        id="result_token_max_uses"
                        type="number"
                        name="result_token_max_uses"
                        min="1"
                        max="50"
                        value="{{ old('result_token_max_uses', $settings->result_token_max_uses) }}"
                        class="mt-1 w-full"
                    >
                    @error('result_token_max_uses')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="min-w-[180px] flex-1">
                    <label for="result_token_expiry_days" class="field-label">Token lifetime (days)</label>
                    <input
                        id="result_token_expiry_days"
                        type="number"
                        name="result_token_expiry_days"
                        min="1"
                        max="730"
                        placeholder="No expiry"
                        value="{{ old('result_token_expiry_days', $settings->result_token_expiry_days) }}"
                        class="mt-1 w-full"
                    >
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Leave blank so tokens never expire on their own.</p>
                    @error('result_token_expiry_days')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600">
                    Save settings
                </button>
            </form>
        </div>

        {{-- Attempts worth a second look: guessing, and rate-limited bursts. --}}
        @if ($suspicious->isNotEmpty())
            <div class="rounded-[5px] border border-red-200 bg-red-50 shadow-sm dark:border-red-900/50 dark:bg-red-900/20 lg:rounded-[10px]">
                <div class="border-b border-red-200 p-5 dark:border-red-900/50">
                    <h2 class="text-sm font-bold text-red-900 dark:text-red-300">Access attempts worth investigating</h2>
                    <p class="mt-0.5 text-xs text-red-800 dark:text-red-400">
                        Tokens that do not exist, revoked tokens being tried, and addresses that hit the rate limit.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-red-900/70 dark:text-red-400/70">
                            <tr>
                                <th class="px-5 py-3 font-semibold">When</th>
                                <th class="px-5 py-3 font-semibold">School</th>
                                <th class="px-5 py-3 font-semibold">Outcome</th>
                                <th class="px-5 py-3 font-semibold">From</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-red-200 dark:divide-red-900/50">
                            @foreach ($suspicious as $entry)
                                <tr>
                                    <td class="px-5 py-2.5 text-xs text-gray-700 dark:text-gray-300">{{ $entry->occurred_at->format('j M, g:ia') }}</td>
                                    <td class="px-5 py-2.5 text-gray-900 dark:text-white">{{ $entry->school?->name ?? 'N/A' }}</td>
                                    <td class="px-5 py-2.5 text-xs font-semibold text-red-700 dark:text-red-400">{{ $entry->outcome->label() }}</td>
                                    <td class="px-5 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $entry->ip_address ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('super-admin.result-pins.index') }}" class="flex flex-wrap items-center gap-3 p-4">
                <div class="relative min-w-[200px] flex-1">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search schools..."
                        class="w-full"
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
                            <th class="px-5 py-3 font-semibold">Tokens</th>
                            <th class="px-5 py-3 font-semibold">Active</th>
                            <th class="px-5 py-3 font-semibold">Revoked</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($schools as $school)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">{{ $school->name }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ number_format($school->result_checking_pins_count) }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ number_format($school->active_pins_count) }}</td>
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ number_format($school->revoked_pins_count) }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('super-admin.result-pins.show', $school) }}" class="rounded-[6px] bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300">
                                        Inspect
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No schools found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($schools->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $schools->links() }}</div>
            @endif
        </div>
    </div>
</x-super-admin-layout>
