@php
    $statusBadge = fn (string $status) => match ($status) {
        'Complete' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'In Progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        default => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
    $actionButtonClasses = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] text-gray-500 transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-50 hover:text-blue-600 disabled:pointer-events-none disabled:opacity-40 dark:text-gray-400 dark:hover:bg-blue-900/30 dark:hover:text-blue-400';
@endphp

<x-dashboard-layout page-title="Results" page-subtitle="View, preview, print, and send each student's result and report card.">
    <div class="space-y-6">
        {{-- The template as it will be generated, before generating any. --}}
        <div class="flex flex-wrap justify-end gap-2">
            {{-- Your saved remarks. You are your school's Principal on
                 ScholarNest, so the library is yours. --}}
            <a
                href="{{ route('results.remark-library.index') }}"
                class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-comment-dots text-[13px]"></i>
                Principal&rsquo;s Remark Library
            </a>

            <a
                href="{{ route('results.template-preview') }}"
                class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-file-lines text-[13px]"></i>
                Preview Report Card Template
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        {{-- Why a result was refused, in the words of what is missing. See
             App\Services\ResultCompleteness. --}}
        @error('repository')
            <div class="rounded-[5px] bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 lg:rounded-[10px]">
                {{ $message }}
            </div>
        @enderror

        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="field-label">Class</label>
                    <select name="class" onchange="this.form.submit()" class="mt-1 w-full">
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Academic Session</label>
                    <select name="session" onchange="this.form.submit()" class="mt-1 w-full">
                        @foreach ($sessionOptions as $sessionOption)
                            <option value="{{ $sessionOption }}" @selected($selectedSession === $sessionOption)>{{ $sessionOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Term</label>
                    <select name="term" onchange="this.form.submit()" class="mt-1 w-full">
                        @foreach ($termOptions as $termOption)
                            <option value="{{ $termOption->value }}" @selected($selectedTerm === $termOption)>{{ $termOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if (! $examination)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No examination recorded yet</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $selectedClass ?? 'This class' }} has no examination for {{ $selectedSession }}, {{ $selectedTerm->label() }}. Create one from the Examinations page first.</p>
            </div>
        @else
            <x-push-to-repository
                :push-class-url="route('results.push-class', $examination)"
                :class-name="$selectedClass"
                :session="$selectedSession"
                :term="$selectedTerm"
                :published-count="$publishedResults->count()"
                :total-count="$students->count()"
                :stale-count="count($staleStudentIds)"
            />

            <div class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1020px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                <th class="px-4 py-3 font-semibold">Student ID</th>
                                <th class="px-4 py-3 font-semibold">Class</th>
                                <th class="px-4 py-3 font-semibold">Session</th>
                                <th class="px-4 py-3 font-semibold">Term</th>
                                <th class="px-4 py-3 font-semibold">Avg. Score</th>
                                <th class="px-4 py-3 font-semibold">Percentage</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Repository</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($students as $row)
                                @php
                                    $student = $row['student'];
                                    $showUrl = route('results.show', [$examination, $student]);
                                    $remarksUrl = route('results.remarks', [$examination, $student]);
                                    $sendUrl = route('results.send', [$examination, $student]);
                                    $printUrl = route('results.print', [$examination, $student]);
                                    $pdfUrl = route('results.pdf', [$examination, $student]);
                                @endphp
                                <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            @if ($student->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                            @else
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <span class="truncate font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedClass }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedSession }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedTerm->label() }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['averageScore'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['average'] !== null ? $row['average'].'%' : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span>
                                    </td>

                                    <x-repository-status-cell
                                        :published="$publishedResults->get($student->id)"
                                        :is-stale="in_array($student->id, $staleStudentIds, true)"
                                        :push-url="route('results.push', [$examination, $student])"
                                    />

                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1" x-data="{ downloading: false }">
                                            <button
                                                type="button"
                                                title="View Result"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'details')"
                                                class="{{ $actionButtonClasses }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.6" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="A4 Preview"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'report-card')"
                                                class="{{ $actionButtonClasses }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 3.5h9l3 3V20a.5.5 0 01-.5.5H6a.5.5 0 01-.5-.5V4a.5.5 0 01.5-.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M15 3.5V7h3.5M8.5 12.5h7M8.5 15.5h7M8.5 9.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="Print"
                                                @click="$store.resultPreview.printDirect('{{ $printUrl }}')"
                                                class="{{ $actionButtonClasses }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 8.5V4a.5.5 0 01.5-.5h10a.5.5 0 01.5.5v4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><path d="M6 17.5H4.75A1.25 1.25 0 013.5 16.25v-5.5A1.25 1.25 0 014.75 9.5h14.5a1.25 1.25 0 011.25 1.25v5.5a1.25 1.25 0 01-1.25 1.25H18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><path d="M6.5 13.5h11v7a.5.5 0 01-.5.5h-10a.5.5 0 01-.5-.5v-7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="Download PDF"
                                                :disabled="downloading"
                                                @click="downloading = true; $store.resultPreview.downloadDirect('{{ $pdfUrl }}', '{{ $student->admission_number }}').finally(() => downloading = false)"
                                                class="{{ $actionButtonClasses }}"
                                            >
                                                <svg x-show="!downloading" class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4v11m0 0l-4-4m4 4l4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /><path d="M5 17.5V19a1.5 1.5 0 001.5 1.5h11A1.5 1.5 0 0019 19v-1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                <svg x-show="downloading" style="display: none;" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.25" /><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" /></svg>
                                            </button>
                                            <button
                                                type="button"
                                                title="Send Result"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'send')"
                                                class="{{ $actionButtonClasses }}"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 12L20 4l-6.5 16-3-6.5L4 12z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $selectedClass }} yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <x-result-preview-modal />
</x-dashboard-layout>
