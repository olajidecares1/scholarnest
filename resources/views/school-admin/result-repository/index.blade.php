{{-- The Result Repository.

     The School Admin's view of what the school has published: which cards are
     in there, when they went in, who put them there, and whether any have been
     corrected since.

     Read-only on purpose. Results are pushed from the Results page, where the
     marks are; this page is where they are looked up. Giving it an edit button
     would make it a second place to change a result, and the whole value of a
     repository is that there is one version of what was approved. --}}
<x-dashboard-layout
    page-title="Result Repository"
    page-subtitle="Results your school has published. These are the results students and pupils see when they check through your result-checking link."
>
    <div class="space-y-6">
        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            {{-- Class, then session, then term, the order asked for, and the
                 order somebody looking for a particular result thinks in. --}}
            <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="field-label">Class</label>
                    <select name="class" onchange="this.form.submit()" class="mt-1 w-full">
                        @forelse ($classOptions as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @empty
                            <option value="">No results published yet</option>
                        @endforelse
                    </select>
                </div>
                <div>
                    <label class="field-label">Academic Year</label>
                    <select name="session" onchange="this.form.submit()" class="mt-1 w-full">
                        @foreach ($sessionOptions as $sessionOption)
                            <option value="{{ $sessionOption }}" @selected($selectedSession === $sessionOption)>{{ $sessionOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Term</label>
                    <select name="term" onchange="this.form.submit()" class="mt-1 w-full">
                        <option value="">Choose a term…</option>
                        @foreach ($termOptions as $termOption)
                            <option value="{{ $termOption->value }}" @selected($selectedTerm === $termOption)>{{ $termOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if ($totalStored === 0)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Nothing published yet</p>
                <small class="block mx-auto mt-1 max-w-lg text-sm text-gray-500 dark:text-gray-400">
                    Results appear here once you or a Class Teacher use <span class="font-semibold">Push to Repository</span>
                    on the Results page. Until then, students checking your result link are told their result is not ready.
                </small>
            </div>
        @elseif ($selectedTerm === null)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Choose a term</p>
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Pick a class, an academic year and a term to see the results stored for it.</small>
            </div>
        @else
            <div
                class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                {{-- Inline rather than a named function in a script block:
                     this layout has no script stack, and an inline object has
                     no load-order to get wrong. --}}
                x-data="{
                    open: false,
                    loading: false,
                    error: null,
                    html: '',
                    meta: '',
                    async view(url) {
                        this.open = true;
                        this.loading = true;
                        this.error = null;
                        this.html = '';
                        this.meta = '';

                        try {
                            const response = await fetch(url, { headers: { Accept: 'application/json' } });

                            if (! response.ok) {
                                throw new Error('That result could not be loaded.');
                            }

                            const data = await response.json();

                            this.html = data.report_card_html;
                            this.meta = 'Pushed ' + data.pushed_at + ' by ' + data.pushed_by
                                + (data.version > 1 ? ' · version ' + data.version : '');
                        } catch (e) {
                            this.error = e.message;
                        } finally {
                            this.loading = false;
                        }
                    },
                }"
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                        {{ $selectedClass }} &mdash; {{ $selectedTerm->label() }}, {{ $selectedSession }}
                    </h2>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        {{ $results->count() }} {{ Str::plural('result', $results->count()) }} stored
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                <th class="px-4 py-3 font-semibold">Student ID</th>
                                <th class="px-4 py-3 font-semibold">Subjects</th>
                                <th class="px-4 py-3 font-semibold">Average</th>
                                <th class="px-4 py-3 font-semibold">Position</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Pushed</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($results as $result)
                                @php
                                    $payload = $result->payload;
                                    $isStale = in_array($result->id, $staleIds, true);
                                @endphp
                                <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                        {{ $payload['student']['full_name'] ?? $result->student?->fullName() }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $payload['student']['admission_number'] ?? 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ count($payload['subjects'] ?? []) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $payload['summary']['average'] !== null ? $payload['summary']['average'].'%' : 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $payload['summary']['position'] ?? 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($isStale)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                Needs updating
                                            </span>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                Published
                                            </span>
                                        @endif

                                        @if ($result->version > 1)
                                            <span class="ml-1 text-[11px] font-semibold text-gray-400">v{{ $result->version }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                        <span class="block">{{ $result->pushed_at->format('M j, Y') }}</span>
                                        <small class="block text-[11px] text-gray-400">
                                            {{ $result->pushed_at->format('g:ia') }} &middot; {{ $result->pushed_by_name }}
                                        </small>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            @click="view('{{ route('result-repository.show', $result) }}')"
                                            class="btn inline-flex h-8 items-center justify-center gap-1.5 rounded-[8px] border border-gray-300 px-3 text-[11.5px] font-bold text-gray-600 transition hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-primary-900/20"
                                        >
                                            <i class="fa-regular fa-eye text-[11px]"></i>
                                            View stored result
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No results have been pushed for {{ $selectedClass }} &mdash; {{ $selectedTerm->label() }}, {{ $selectedSession }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- A modal of its own rather than the Results page's preview.
                     That one carries remark editing, sending and printing; none
                     of those belong on a page whose whole point is that what is
                     stored does not change. --}}
                <div
                    x-show="open"
                    x-cloak
                    @keydown.escape.window="open = false"
                    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/60 p-4 sm:p-8"
                >
                    <div @click.outside="open = false" class="w-full max-w-4xl rounded-[10px] bg-white shadow-xl dark:bg-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Stored result</h3>
                                <small class="block mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400" x-text="meta"></small>
                            </div>
                            <button type="button" @click="open = false" class="text-gray-400 transition hover:text-gray-600">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="max-h-[75vh] overflow-y-auto p-5">
                            <small x-show="loading" class="block py-10 text-center text-sm text-gray-500">Loading…</small>
                            <p x-show="error" x-cloak class="py-10 text-center text-sm text-red-600" x-text="error"></p>
                            <div x-show="!loading && !error" x-html="html"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

</x-dashboard-layout>
