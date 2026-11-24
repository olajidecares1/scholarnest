{{--
    One list of people to make ID cards for: a class, a search result, the
    teaching or the non-teaching staff.

    For the whole list (or, for staff, everyone the department filter shows)
    or only those ticked: Generate (issue their cards, no printing), Preview
    & print (all their cards on one page, to check, then print), and PDF.
    Each person: their card's status, and Preview, Print and PDF for them
    alone.

    $type        student | teaching_staff | non_teaching_staff
    $people      Collection<Student|Staff>
    $templates   that type's card templates
    $cards       IssuedIdCard keyed by holder uuid
    $title       heading;  $subtitle (optional)
    $detailLabel / $detail  the third column (class, department) and its value
    $rowFilter   (optional) fn ($person): an Alpine expression that hides a row
    $emptyText
--}}
@php
    $isStudent = $type === 'student';
    $idLabel = $isStudent ? 'Admission No.' : 'Staff ID';
    $active = $people->filter(fn ($person) => $cards->get($person->uuid)?->status === \App\Enums\IssuedIdCardStatus::Active)->count();
    $rowFilter ??= null;
    $single = $people->count() === 1;
    $noun = $isStudent ? ($people->count() === 1 ? 'student/pupil' : 'students/pupils') : ($people->count() === 1 ? 'staff member' : 'staff members');
@endphp

<section
    class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    x-data="{
        selected: [],
        autoprint: false,
        visible() {
            return [...this.$root.querySelectorAll('tbody tr[data-uuid]')]
                .filter((row) => row.style.display !== 'none')
                .map((row) => row.dataset.uuid);
        },
        toggleAll(on) {
            this.selected = on ? this.visible() : [];
        },
        submitAs(action, target, autoprint = false) {
            const form = this.$refs.form;
            this.autoprint = autoprint;
            form.action = action;
            form.target = target;
            this.$nextTick(() => form.submit());
        },
        everyone(action, target, autoprint = false) {
            this.selected = this.visible();
            this.submitAs(action, target, autoprint);
        },
        only(uuid, action, target, autoprint = false) {
            const kept = this.selected;
            this.selected = [uuid];
            this.submitAs(action, target, autoprint);
            setTimeout(() => { this.selected = kept; }, 400);
        },
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
        <div class="min-w-0">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
            <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                {{ $subtitle ?? ($people->count().' '.$noun.' · '.$active.' with an ID card') }}
            </small>
        </div>
    </div>

    @if ($templates->isEmpty())
        <div class="m-5 rounded-[10px] border border-amber-200 bg-amber-50 p-5 text-center text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
            There is no card template for {{ $isStudent ? 'students' : ($type === 'teaching_staff' ? 'teaching staff' : 'non-teaching staff') }} yet.
            <a href="{{ route('id-cards.templates.index') }}" class="font-semibold underline">Create one</a> first.
        </div>
    @elseif ($people->isEmpty())
        <small class="block px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyText }}</small>
    @else
        <form method="POST" action="{{ route('id-cards.generate') }}" x-ref="form">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="autoprint" :value="autoprint ? 1 : 0">

            <div class="space-y-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="min-w-[12rem] flex-1 sm:flex-none">
                        <span class="field-label mb-1">Card template</span>
                        <select name="template" class="w-full">
                            @foreach ($templates as $template)
                                <option value="{{ $template->uuid }}" @selected($template->is_default)>{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                {{-- Everyone in the list (that the filter shows). --}}
                <div class="flex flex-wrap items-center gap-2">
                    @unless ($single)
                        <small class="mr-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">All {{ $people->count() }}</small>
                    @endunless
                    <button type="button" @click="everyone(@js(route('id-cards.generate')), '_self')" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-id-card btn-icon" aria-hidden="true"></i> {{ $single ? 'Generate card' : 'Generate all' }}
                    </button>
                    <button type="button" @click="everyone(@js(route('id-cards.print')), '_blank')" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i> {{ $single ? 'Preview & print' : 'Preview & print all' }}
                    </button>
                    <button type="button" @click="everyone(@js(route('id-cards.pdf')), '_self')" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-file-pdf btn-icon" aria-hidden="true"></i> {{ $single ? 'Download PDF' : 'PDF of all' }}
                    </button>
                </div>

                {{-- Only those ticked. --}}
                <div class="flex flex-wrap items-center gap-2" x-show="selected.length > 0 && ! @js($single)" x-cloak>
                    <small class="mr-1 text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400"><span x-text="selected.length"></span> ticked</small>
                    <button type="button" @click="submitAs(@js(route('id-cards.generate')), '_self')" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-id-card btn-icon" aria-hidden="true"></i> Generate ticked
                    </button>
                    <button type="button" @click="submitAs(@js(route('id-cards.print')), '_blank')" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i> Preview &amp; print ticked
                    </button>
                    <button type="button" @click="submitAs(@js(route('id-cards.pdf')), '_self')" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-file-pdf btn-icon" aria-hidden="true"></i> PDF of ticked
                    </button>
                    <button type="button" @click="selected = []" class="text-xs font-semibold text-gray-500 underline hover:text-gray-700 dark:text-gray-400">Clear</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="stack-cards w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" aria-label="Tick everyone shown" @change="toggleAll($event.target.checked)" :checked="selected.length > 0 && selected.length === visible().length">
                            </th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ $idLabel }}</th>
                            <th class="px-4 py-3 font-semibold">{{ $detailLabel }}</th>
                            <th class="px-4 py-3 font-semibold">ID Card</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($people as $person)
                            @php
                                $card = $cards->get($person->uuid);
                                $identifier = $isStudent ? $person->admission_number : $person->staff_number;
                            @endphp
                            <tr data-uuid="{{ $person->uuid }}" @if ($rowFilter) x-show="{{ $rowFilter($person) }}" @endif>
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="records[]" value="{{ $person->uuid }}" x-model="selected" aria-label="Tick {{ $person->fullName() }}">
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $person->fullName() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $identifier }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $detail($person) ?: 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    @if ($card?->status === \App\Enums\IssuedIdCardStatus::Active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $card->card_number }}
                                        </span>
                                        <small class="mt-0.5 block text-[11px] text-gray-400">Issued {{ $card->issued_at?->format('j M Y') }}</small>
                                    @elseif ($card)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Revoked</span>
                                        <small class="mt-0.5 block text-[11px] text-gray-400">Generate to reissue</small>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300">Not generated</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" @click="$store.idCardPreview.openPreview(@js(route('id-cards.preview', [$type, $person])), @js(route('id-cards.print')), @js(route('id-cards.pdf')))" class="icon-btn icon-btn--view" data-tooltip="Preview card" aria-label="Preview {{ $person->fullName() }}'s card"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                        <button type="button" @click="only(@js($person->uuid), @js(route('id-cards.print')), '_blank', true)" class="icon-btn icon-btn--neutral" data-tooltip="Print card" aria-label="Print {{ $person->fullName() }}'s card"><i class="fa-solid fa-print" aria-hidden="true"></i></button>
                                        <button type="button" @click="only(@js($person->uuid), @js(route('id-cards.pdf')), '_self')" class="icon-btn icon-btn--neutral" data-tooltip="Download PDF" aria-label="Download {{ $person->fullName() }}'s card as PDF"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    @endif
</section>
