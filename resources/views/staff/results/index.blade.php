@php
    $statusBadge = fn (string $status) => match ($status) {
        'Complete' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'In Progress' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        default => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
    $actionButtonClasses = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] text-gray-500 transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-50 hover:text-primary-600 disabled:pointer-events-none disabled:opacity-40 dark:text-gray-400 dark:hover:bg-primary-900/30 dark:hover:text-primary-400';
@endphp

{{-- A Class Teacher READS their class's report cards and writes the teacher's
     remark. They do not print them, download them, or see a pupil's Student
     ID here: issuing a document is the school's job, and the teacher who
     marks it is not the one who hands it out.

     The routes behind print and pdf are gone from routes/staff.php, so this
     is not a page with its buttons taken off. --}}
<x-staff-layout page-title="Report Cards" page-subtitle="View and preview your class's report cards, and write each pupil's comment.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @error('repository')
            <div class="rounded-[5px] bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 lg:rounded-[10px]">
                {{ $message }}
            </div>
        @enderror

        @if ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Ask your School Admin to assign you as the Class Teacher of a class to view report cards.</small>
            </div>
        @else
            <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="field-label">Class</label>
                        @if ($classes->count() > 1)
                            <select name="class" onchange="this.form.submit()" class="mt-1 w-full">
                                @foreach ($classes as $class)
                                    <option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="mt-1 rounded-[8px] border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">{{ $className }}</p>
                        @endif
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
                    <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $className }} has no examination for {{ $selectedSession }}, {{ $selectedTerm->label() }} yet.</small>
                </div>
            @else
                <x-push-to-repository
                    :push-class-url="route('staff.results.push-class', [$school, $examination])"
                    :class-name="$className"
                    :session="$selectedSession"
                    :term="$selectedTerm"
                    :published-count="$publishedResults->count()"
                    :total-count="$students->count()"
                    :stale-count="count($staleStudentIds)"
                />

                {{-- The teacher's saved comments, read once by the savedRemarks
                     store (resources/js/teacher-remark-box.js) and offered
                     under every pupil's comment box. --}}
                @php
                    $savedRemarksData = [
                        'items' => $savedRemarks,
                        'suggested' => $suggestedRemarks,
                        'forgetUrl' => route('staff.results.saved-remarks.destroy', [$school, '__REMARK__']),
                        'keepUrl' => route('staff.results.saved-remarks.store', $school),
                    ];
                @endphp
                <script type="application/json" id="saved-remarks-data">{!! json_encode($savedRemarksData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

                <div class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="stack-cards w-full min-w-[900px] text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="w-12 whitespace-nowrap px-4 py-3 font-semibold">S/N</th>
                                    <th class="px-4 py-3 font-semibold">Student &amp; Comment</th>
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
                                        $showUrl = route('staff.results.show', [$school, $examination, $student]);
                                        $remarksUrl = route('staff.results.remarks', [$school, $examination, $student]);
                                    @endphp
                                    <tr class="align-top transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <td class="w-12 px-4 py-3 tabular-nums text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2.5">
                                                @if ($student->photoUrl())
                                                    <img data-fallback="{{ $student->initials() }}" src="{{ $student->photoUrl() }}" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                                @else
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                                        {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                    </span>
                                                @endif
                                                <span class="min-w-0">
                                                    <span class="block truncate font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                                    <span class="block truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $student->admission_number ?: 'No admission no.' }}</span>
                                                </span>
                                            </div>

                                            {{-- The Class Teacher's comment for this pupil's report card:
                                                 the "Class Teacher's Remark" the card prints. Saves itself
                                                 as the teacher types; see resources/js/teacher-remark-box.js. --}}
                                            <div
                                                class="mt-2 w-full sm:w-80"
                                                x-data="teacherRemarkBox({ url: @js($remarksUrl), initial: @js($remarks->get($student->id) ?? ''), published: @js($publishedResults->has($student->id)) })"
                                                x-on:teacher-remark-saved.window="syncFrom($event)"
                                            >
                                                <label for="remark-{{ $student->uuid }}" class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Class Teacher's Comment</label>
                                                <textarea
                                                    id="remark-{{ $student->uuid }}"
                                                    x-model="text"
                                                    x-on:input="queue()"
                                                    x-on:blur="save()"
                                                    rows="2"
                                                    maxlength="1000"
                                                    placeholder="Write a comment for {{ $student->first_name }}'s report card…"
                                                    class="mt-0.5 w-full resize-y text-sm"
                                                >{{ $remarks->get($student->id) }}</textarea>
                                                <div class="mt-0.5 flex items-center justify-between gap-2 text-[11px]" aria-live="polite">
                                                    <span>
                                                        <span x-show="state === 'saving'" x-cloak class="text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-0.5" aria-hidden="true"></i> Saving…</span>
                                                        <span x-show="state === 'saved'" x-cloak class="text-green-600 dark:text-green-400"><i class="fa-solid fa-check mr-0.5" aria-hidden="true"></i> Saved</span>
                                                        <span x-show="state === 'error'" x-cloak class="text-red-600 dark:text-red-400">
                                                            Not saved. <button type="button" class="font-semibold underline" x-on:click="save()">Try again</button>
                                                        </span>
                                                    </span>
                                                    <span class="tabular-nums text-gray-400" x-text="text.length + '/1000'"></span>
                                                </div>
                                                <p x-show="needsRepush" x-cloak class="mt-0.5 text-[11px] text-amber-700 dark:text-amber-400">
                                                    This card is already in the Repository. Push it again so the published card shows this comment.
                                                </p>

                                                {{-- Save keeps this pupil's comment on the teacher's list for
                                                     good. Comments opens that list, the teacher's own comments
                                                     first, then thirty ready-made ones: tap one and it is this
                                                     pupil's comment, saved at once. The list sits in the flow of
                                                     the row rather than floating, so the table's scroll box
                                                     cannot clip it. --}}
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <button type="button" x-on:click="keepCurrent()" :disabled="text.trim() === '' || keepState === 'saving'" class="inline-flex items-center gap-1.5 rounded-full bg-primary-600 px-3 py-1 text-[11.5px] font-semibold text-white shadow-sm transition-colors duration-150 hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50" data-tooltip="Save this comment and keep it in your list">
                                                        <i class="fa-solid text-[10px]" :class="keepState === 'saving' ? 'fa-spinner fa-spin' : 'fa-floppy-disk'" aria-hidden="true"></i>
                                                        Save
                                                    </button>

                                                    <button type="button" x-on:click="togglePicker()" :aria-expanded="pickerOpen" class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[11.5px] font-semibold text-primary-700 transition-colors duration-150 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                                                        <i class="fa-solid fa-list-check text-[10px]" aria-hidden="true"></i>
                                                        Comments
                                                        <span class="rounded-full bg-white px-1.5 text-[10px] tabular-nums dark:bg-primary-900/60" x-text="$store.savedRemarks.total"></span>
                                                        <i class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200" :class="pickerOpen && 'rotate-180'" aria-hidden="true"></i>
                                                    </button>

                                                    <span x-show="keepState === 'kept'" x-transition.opacity x-cloak class="text-[11px] font-medium text-green-600 dark:text-green-400"><i class="fa-solid fa-check mr-0.5" aria-hidden="true"></i> Saved to your comments</span>
                                                    <span x-show="keepState === 'error'" x-cloak class="text-[11px] font-medium text-red-600 dark:text-red-400">Not saved. Try again.</span>
                                                </div>

                                                <div x-show="pickerOpen" x-transition.opacity.duration.150ms x-cloak x-on:keydown.escape="pickerOpen = false" class="mt-1.5 overflow-hidden rounded-[8px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                                    <div class="border-b border-gray-100 p-1.5 dark:border-gray-700">
                                                        <input type="search" x-model="search" placeholder="Search comments" aria-label="Search comments" class="w-full text-xs">
                                                    </div>

                                                    <div class="max-h-64 overflow-y-auto overscroll-contain">
                                                        <p x-show="choices.length > 0" class="sticky top-0 z-10 bg-gray-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">Your comments</p>
                                                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            <template x-for="choice in choices" :key="choice.uuid">
                                                                <li class="flex items-start gap-1">
                                                                    <button type="button" x-on:click="pick(choice)" class="min-w-0 flex-1 px-2.5 py-2 text-left text-[12.5px] leading-snug text-gray-700 transition-colors duration-150 hover:bg-primary-50 hover:text-primary-800 dark:text-gray-200 dark:hover:bg-primary-900/30" x-text="choice.body"></button>
                                                                    <button type="button" x-on:click="if (confirm('Take this comment off your list? Report cards that already have it keep it.')) $store.savedRemarks.forget(choice)" class="shrink-0 px-2 py-2 text-gray-300 transition-colors duration-150 hover:text-red-600 dark:text-gray-600" data-tooltip="Remove from list" aria-label="Remove from your comments">
                                                                        <i class="fa-solid fa-xmark text-[11px]" aria-hidden="true"></i>
                                                                    </button>
                                                                </li>
                                                            </template>
                                                        </ul>

                                                        <p x-show="suggestions.length > 0" class="sticky top-0 z-10 bg-gray-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">Suggested comments</p>
                                                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            <template x-for="choice in suggestions" :key="choice.uuid">
                                                                <li>
                                                                    <button type="button" x-on:click="pick(choice)" class="w-full px-2.5 py-2 text-left text-[12.5px] leading-snug text-gray-700 transition-colors duration-150 hover:bg-primary-50 hover:text-primary-800 dark:text-gray-200 dark:hover:bg-primary-900/30" x-text="choice.body"></button>
                                                                </li>
                                                            </template>
                                                        </ul>

                                                        <p x-show="choices.length === 0 && suggestions.length === 0" class="px-2.5 py-2 text-[12px] text-gray-500 dark:text-gray-400">No comment matches.</p>
                                                    </div>

                                                    {{-- A new comment straight onto the list, for this pupil
                                                         or anyone after: Save keeps it, it does not touch
                                                         this pupil's card. --}}
                                                    <div class="border-t border-gray-100 p-1.5 dark:border-gray-700">
                                                        <label for="new-comment-{{ $student->uuid }}" class="px-1 text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Add a new comment</label>
                                                        <div class="mt-0.5 flex items-start gap-1.5">
                                                            <textarea id="new-comment-{{ $student->uuid }}" x-model="draft" rows="2" maxlength="1000" placeholder="Type a comment to keep in your list" class="min-w-0 flex-1 resize-y text-xs"></textarea>
                                                            <button type="button" x-on:click="keepDraft()" :disabled="draft.trim() === '' || keepState === 'saving'" class="btn shrink-0 rounded-[6px] bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors duration-150 hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">Save</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['averageScore'] ?? 'N/A' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['average'] !== null ? $row['average'].'%' : 'N/A' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span>
                                        </td>

                                        <x-repository-status-cell
                                            :published="$publishedResults->get($student->id)"
                                            :is-stale="in_array($student->id, $staleStudentIds, true)"
                                            :push-url="route('staff.results.push', [$school, $examination, $student])"
                                        />

                                        {{-- View and A4 preview only.

                                             No Print and no Download: a report
                                             card is a document the school
                                             issues, and the teacher who marks
                                             it is not the one who hands it
                                             out. The routes behind both are
                                             gone as well, so this is not a
                                             pair of buttons taken off a page
                                             that still answers. --}}
                                        <td class="px-4 py-3 text-right">
                                            <div class="icon-btn-group justify-end">
                                                <button
                                                    type="button"
                                                    data-tooltip="View result"
                                                    aria-label="View result"
                                                    @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '', '', '', 'details')"
                                                    class="icon-btn icon-btn--view"
                                                >
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                </button>
                                                <button
                                                    type="button"
                                                    data-tooltip="A4 preview"
                                                    aria-label="A4 preview"
                                                    @click="$store.resultPreview.openPreview('{{ $showUrl }}', '{{ $remarksUrl }}', '', '', '', 'report-card')"
                                                    class="icon-btn icon-btn--view"
                                                >
                                                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $className }} yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <x-result-preview-modal :can-send="false" :can-print="false" :can-download="false" />
</x-staff-layout>
