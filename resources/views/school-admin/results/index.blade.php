@php
    $statusBadge = fn (string $status) => match ($status) {
        'Complete' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'In Progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        default => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

<x-dashboard-layout page-title="Results" page-subtitle="View, preview, print, and send each student's result and report card.">
    <div class="space-y-6">
        {{-- The template as it will be generated, before generating any. --}}
        <div class="flex flex-wrap justify-end gap-2">
            {{-- Your saved remarks. You are your school's Principal on
                 AkademicNest, so the library is yours. --}}
            <a
                href="{{ route('results.remark-library.index') }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-comment-dots text-[13px]"></i>
                Principal&rsquo;s Remark Library
            </a>

            {{-- Every saved result, by session, term and class, and any one
                 student's whole record by admission number. --}}
            <a
                href="{{ route('results.archive') }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-box-archive text-[13px]"></i>
                All Saved Results
            </a>

            {{-- Only for a school that has turned cumulative results on, in
                 Academics. Every plan. --}}
            @if (auth()->user()->school->usesCumulativeResults())
                <a
                    href="{{ route('results.session', ['class' => $selectedClass, 'session' => $selectedSession]) }}"
                    class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
                >
                    <i class="fa-solid fa-layer-group text-[13px]"></i>
                    Session Results
                </a>
            @endif

            <a
                href="{{ route('results.template-preview') }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
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
        @error('send')
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                {{ $message }}
            </div>
        @enderror

        @error('download')
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                {{ $message }}
            </div>
        @enderror

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
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $selectedClass ?? 'This class' }} has no examination for {{ $selectedSession }}, {{ $selectedTerm->label() }}. Create one from the Examinations page first.</small>
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

            {{-- Result details (result link, token and admission number) to
                 parents and guardians: the whole class, the ticked students,
                 or one student from their row. Only tokens the School Admin
                 generated are sent; nothing is issued at sending. --}}
            <div
                class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                x-data="{
                    picked: [],
                    all: @js($students->map(fn ($row) => $row['student']->uuid)->values()),
                    get allPicked() { return this.all.length > 0 && this.picked.length === this.all.length },
                }"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <small class="text-xs text-gray-500 dark:text-gray-400">
                        <span x-show="picked.length === 0">Tick students to download their report cards or send their result details to parents/guardians, or do it for the whole class.</span>
                        <span x-show="picked.length > 0" x-cloak class="font-semibold text-gray-800 dark:text-gray-100"><span x-text="picked.length"></span> selected</span>
                    </small>
                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Every pupil in the class at once. The tick box in the
                             table's heading does the same, but a phone shows the
                             list as cards with no heading row, so this is the one
                             that is always there. --}}
                        <button type="button" x-on:click="picked = allPicked ? [] : [...all]" :disabled="all.length === 0" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700" :aria-pressed="allPicked">
                            <i class="fa-regular" :class="allPicked ? 'fa-square-check' : 'fa-square'" aria-hidden="true"></i>
                            <span x-show="!allPicked">Select all ({{ $students->count() }})</span>
                            <span x-show="allPicked" x-cloak>Clear selection</span>
                        </button>

                        {{-- Report cards to keep: the whole class, or the ticked
                             pupils, as one PDF with a card to a page. A plain
                             download, so it saves the same way on a phone, a
                             tablet or a laptop. --}}
                        <form method="GET" action="{{ route('results.class-pdf', $examination) }}" x-data="{ preparing: false }" x-on:submit="preparing = true; setTimeout(() => preparing = false, 8000)">
                            <template x-for="id in picked" :key="id">
                                <input type="hidden" name="students[]" :value="id">
                            </template>
                            <button type="submit" :disabled="all.length === 0" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="One PDF, a report card to a page">
                                <i class="fa-solid" :class="preparing ? 'fa-spinner fa-spin' : 'fa-file-arrow-down'" aria-hidden="true"></i>
                                <span x-show="preparing" x-cloak>Preparing PDF&hellip;</span>
                                <span x-show="!preparing && picked.length === 0">Download {{ $selectedClass }} results</span>
                                <span x-show="!preparing && picked.length > 0" x-cloak>Download selected (<span x-text="picked.length"></span>)</span>
                            </button>
                        </form>
                        <a href="{{ route('result-pins.details.history') }}" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Sending history
                        </a>
<form method="GET" action="{{ route('result-pins.details.broadcast', $examination->uuid) }}" class="inline-block">
                            {{-- The ticked pupils, or the whole class when none is ticked. --}}
                            <template x-for="id in picked" :key="id">
                                <input type="hidden" name="students[]" :value="id">
                            </template>
                            <button type="submit" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-[#25D366] px-3 py-2 text-xs font-semibold text-white hover:bg-[#1ebe5b]" data-tooltip="Each parent gets their own child's name, admission number, token and result link on WhatsApp">
                                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                                <span x-show="picked.length === 0">WhatsApp broadcast</span>
                                <span x-show="picked.length > 0" x-cloak>WhatsApp broadcast (<span x-text="picked.length"></span>)</span>
                            </button>
                        </form>
                        <div x-show="picked.length > 0" x-cloak>
                            <x-send-result-details :action="route('result-pins.details.preview')" label="Send to selected parents">
                                <input type="hidden" name="examination" value="{{ $examination->uuid }}">
                                <template x-for="id in picked" :key="id">
                                    <input type="hidden" name="students[]" :value="id">
                                </template>
                            </x-send-result-details>
                        </div>
                        <div x-show="picked.length === 0">
                            <x-send-result-details :action="route('result-pins.details.preview')" label="Send to {{ $selectedClass }} parents">
                                <input type="hidden" name="examination" value="{{ $examination->uuid }}">
                                <input type="hidden" name="all" value="1">
                            </x-send-result-details>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="stack-cards w-full min-w-[1020px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="w-10 py-3 pl-4 pr-0">
                                    <input type="checkbox" :checked="allPicked" x-on:change="picked = $event.target.checked ? [...all] : []" :disabled="all.length === 0" aria-label="Select every student">
                                </th>
                                <th class="w-12 px-4 py-3 font-semibold">S/N</th>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                <th class="px-4 py-3 font-semibold">Admission No.</th>
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
                                    <td class="w-10 py-3 pl-4 pr-0">
                                        <input type="checkbox" value="{{ $student->uuid }}" x-model="picked" aria-label="Select {{ $student->fullName() }}">
                                    </td>
                                    <td class="w-12 px-4 py-3 tabular-nums text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            @if ($student->photoUrl())
                                                <img data-fallback="{{ $student->initials() }}" src="{{ $student->photoUrl() }}" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                            @else
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <a href="{{ route('results.student', ['admission_number' => $student->admission_number]) }}" class="truncate font-semibold text-gray-900 hover:text-primary-600 hover:underline dark:text-white" data-tooltip="All results for this student">{{ $student->fullName() }}</a>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedClass }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedSession }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $selectedTerm->label() }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['averageScore'] ?? 'N/A' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['average'] !== null ? $row['average'].'%' : 'N/A' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span>
                                    </td>

                                    <x-repository-status-cell
                                        :published="$publishedResults->get($student->id)"
                                        :is-stale="in_array($student->id, $staleStudentIds, true)"
                                        :push-url="route('results.push', [$examination, $student])"
                                    />

                                    <td class="px-4 py-3 text-right">
                                        <div class="icon-btn-group justify-end" x-data="{ downloading: false }">
                                            <button
                                                type="button"
                                                data-tooltip="View Result"
                                                aria-label="View Result"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'details')"
                                                class="icon-btn icon-btn--view"
                                            >
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                data-tooltip="A4 Preview"
                                                aria-label="A4 Preview"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'report-card')"
                                                class="icon-btn icon-btn--view"
                                            >
                                                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                data-tooltip="Print"
                                                aria-label="Print"
                                                @click="$store.resultPreview.printDirect('{{ $printUrl }}')"
                                                class="icon-btn icon-btn--download"
                                            >
                                                <i class="fa-solid fa-print" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                data-tooltip="Download PDF"
                                                aria-label="Download PDF"
                                                :disabled="downloading"
                                                @click="downloading = true; $store.resultPreview.downloadDirect('{{ $pdfUrl }}', '{{ $student->admission_number }}').finally(() => downloading = false)"
                                                class="icon-btn icon-btn--download"
                                            >
                                                <i x-show="!downloading" class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                                <i x-show="downloading" style="display: none;" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                            </button>
                                            <button
                                                type="button"
                                                data-tooltip="Send Result"
                                                aria-label="Send Result"
                                                @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '{{ $sendUrl }}', '{{ $printUrl }}', '{{ $pdfUrl }}', 'send')"
                                                class="icon-btn icon-btn--view"
                                            >
                                                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                            </button>
                                            <x-send-result-details :action="route('result-pins.details.send')" :compact="true" icon="fa-share-from-square" label="Send result link, token and admission no. to parents">
                                                <input type="hidden" name="examination" value="{{ $examination->uuid }}">
                                                <input type="hidden" name="students[]" value="{{ $student->uuid }}">
                                            </x-send-result-details>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $selectedClass }} yet.</td>
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
