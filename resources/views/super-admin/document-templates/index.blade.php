<x-super-admin-layout page-title="Templates" page-subtitle="The report card and ID card exactly as schools receive them.">
    <div class="space-y-6" x-data="{ tab: 'report-card' }">
        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm font-bold text-gray-900 dark:text-white">These are the real templates, not mock-ups</p>
            <p class="field-hint mt-1">
                Both previews render the same Blade templates that produce a printed report card and a printed ID card.
                Every school-specific detail below &mdash; crest, colours, watermark, address, pupil, marks, grades and remarks
                &mdash; is filled in from the chosen school's own record, so what a school receives is what you see here.
            </p>

            <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-[220px]">
                    <label class="field-label" for="school">Preview as school</label>
                    <select id="school" name="school" class="mt-1.5 w-full" onchange="this.form.submit()">
                        @forelse ($schools as $option)
                            <option value="{{ $option->uuid }}" @selected($option->id === $school->id)>{{ $option->name }}</option>
                        @empty
                            <option>No schools registered yet</option>
                        @endforelse
                    </select>
                </div>

                <div class="min-w-[180px]">
                    <label class="field-label" for="holder">ID card for</label>
                    <select id="holder" name="holder" class="mt-1.5 w-full" onchange="this.form.submit()">
                        @foreach (\App\Enums\IdCardHolderType::cases() as $case)
                            <option value="{{ $case->value }}" @selected($case === $holderType)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <noscript>
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Show</button>
                </noscript>
            </form>
        </div>

        <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
            @foreach ([['report-card', 'Report Card'], ['id-card', 'ID Card']] as [$key, $label])
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'"
                    class="rounded-[6px] px-4 py-1.5 text-xs font-semibold transition-colors duration-200"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- The report card, at the width an A4 page is rendered to. --}}
        <div x-show="tab === 'report-card'" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-gray-900 dark:text-white">Report card &mdash; A4 portrait</p>
                <p class="field-hint">Watermark uses {{ $school->name }}'s own logo.</p>
            </div>

            <div class="overflow-x-auto rounded-[10px] bg-gray-100 p-4 dark:bg-gray-900">
                <div class="mx-auto" style="width: 794px;">
                    @include('school-admin.results._report-card', $reportCard)
                </div>
            </div>
        </div>

        {{-- The ID card, front and back, at twice size so it can be read. --}}
        <div x-show="tab === 'id-card'" style="display: none;" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-bold text-gray-900 dark:text-white">ID card &mdash; {{ $holderType->label() }}, portrait</p>
                <p class="field-hint">Front and back, shown at twice actual size.</p>
            </div>

            <div class="overflow-x-auto rounded-[10px] bg-gray-100 p-6 dark:bg-gray-900">
                <div class="flex flex-wrap justify-center gap-10">
                    @foreach ([['Front', 'school-admin.id-cards._card'], ['Back', 'school-admin.id-cards._card_back']] as [$face, $partial])
                        <div class="text-center">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $face }}</p>
                            <div style="width: 409px; height: 648px;">
                                <div style="transform: scale(2); transform-origin: top left;">
                                    @include($partial, ['card' => $idCard])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-super-admin-layout>
