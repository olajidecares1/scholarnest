<x-super-admin-layout
    :page-title="$school->name.' | Result Tokens'"
    page-subtitle="Every token this school has issued, and every attempt to redeem one."
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300 lg:rounded-[10px]">
            {{-- Worth stating plainly on this screen, because the platform used
                 to mint these and the habit may linger. --}}
            Schools issue their own tokens. The platform does not generate them, and cannot read
            one back &mdash; only the issuing school can. What it can do is revoke.
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issued tokens</h2>

                <form method="GET" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()" >
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Student</th>
                            <th class="px-5 py-3 font-semibold">Result</th>
                            <th class="px-5 py-3 font-semibold">Views</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Issued</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($tokens as $token)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $token->boundStudent?->fullName() ?? 'N/A' }}</p>
                                    <small class="field-hint">{{ $token->boundStudent?->admission_number }}</small>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($token->examination)
                                        {{ $token->examination->name }}<br>
                                        {{ $token->examination->term->label() }} &middot; {{ $token->examination->session }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $token->uses_count }} / {{ $token->max_uses }}</td>
                                <td class="px-5 py-3">
                                    @php($colour = $token->displayStatusColour())
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        @class([
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $colour === 'green',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $colour === 'amber',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $colour === 'red',
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $colour === 'gray',
                                        ])">
                                        {{ $token->displayStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $token->issued_at?->format('j M Y') ?? 'N/A' }}</td>
                                <td class="px-5 py-3">
                                    @if ($token->status->allowsAccess() || $token->status->isReversible())
                                        <form method="POST" action="{{ route('super-admin.result-pins.revoke', $token) }}" onsubmit="return confirm('Revoke this token? It will stop working immediately and the school will have to reissue.');">
                                            @csrf
                                            <button type="submit" class="btn rounded-[6px] bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300">Revoke</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">This school has not issued any result tokens yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tokens->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $tokens->links() }}</div>
            @endif
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Result access log</h2>
                <small class="field-hint mt-0.5">Every attempt against this school, successful or not.</small>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">When</th>
                            <th class="px-5 py-3 font-semibold">Student</th>
                            <th class="px-5 py-3 font-semibold">Result</th>
                            <th class="px-5 py-3 font-semibold">Outcome</th>
                            <th class="px-5 py-3 font-semibold">From</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($accessLogs as $entry)
                            <tr>
                                <td class="px-5 py-2.5 text-xs text-gray-600 dark:text-gray-300">{{ $entry->occurred_at->format('j M Y, g:ia') }}</td>
                                <td class="px-5 py-2.5 text-gray-900 dark:text-white">{{ $entry->student?->fullName() ?? 'N/A' }}</td>
                                <td class="px-5 py-2.5 text-xs text-gray-600 dark:text-gray-300">{{ $entry->examination?->name ?? 'N/A' }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        @class([
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $entry->outcome->succeeded(),
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $entry->outcome->isSuspicious(),
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $entry->outcome->succeeded() && ! $entry->outcome->isSuspicious(),
                                        ])">
                                        {{ $entry->outcome->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $entry->ip_address ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No result access recorded for this school.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-super-admin-layout>
