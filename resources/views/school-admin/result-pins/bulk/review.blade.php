@php
    $rows = $groups->flatten(1);
@endphp

<x-dashboard-layout
    page-title="Review Exam Tokens"
    page-subtitle="Check every student before the tokens are generated. Remove anyone who should not receive one."
>
    {{-- Step 2 of 3: review. Nothing has been created yet.

         Removing a student here only unticks them: the confirm button posts
         the remaining ids and a count per class, and the server checks both
         again before it issues a single token. --}}
    <div
        class="space-y-6"
        x-data="{
            removed: {},
            classes: @js($groups->map(fn ($students) => $students->pluck('id')->all())),
            remaining(name) { return this.classes[name].filter((id) => ! this.removed[id]).length },
            get ids() { return Object.values(this.classes).flat().filter((id) => ! this.removed[id]) },
            get total() { return this.ids.length },
            get backUrl() {
                const params = new URLSearchParams({ session: @js($session), term: @js($term->value) });
                this.ids.forEach((id) => params.append('selected[]', id));

                return @js(route('result-pins.bulk.create')) + '?' + params.toString();
            },
        }"
    >
        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">1. Choose students</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-primary-600 px-3 py-1 text-white">2. Review</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">3. Generate &amp; print</span>
        </div>

        {{-- The totals, first, because that is the number the admin has in
             mind: ten from Basic 5, fifteen from Basic 6. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Summary</h2>
                    <small class="field-hint mt-0.5">{{ $term->label() }}, {{ $session }}</small>
                </div>
                <p class="text-right">
                    <span class="block text-3xl font-bold text-primary-700 dark:text-primary-300" x-text="total">{{ $rows->count() }}</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">token<span x-show="total !== 1">s</span> to generate</span>
                </p>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Class</th>
                            <th class="py-2 text-right font-semibold">Students</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($groups as $className => $students)
                            <tr>
                                <td class="py-2 pr-4 font-semibold text-gray-900 dark:text-white">{{ $className }}</td>
                                <td class="py-2 text-right font-mono font-bold text-gray-900 dark:text-white" x-text="remaining(@js($className))">{{ $students->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
                        <tr>
                            <td class="py-2 pr-4 font-bold text-gray-900 dark:text-white">Total</td>
                            <td class="py-2 text-right font-mono font-bold text-gray-900 dark:text-white" x-text="total">{{ $rows->count() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($alreadyHolding->isNotEmpty())
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300">
                <p class="font-semibold">{{ $alreadyHolding->count() }} selected student(s) already hold a valid token for this term and will not get a second one:</p>
                <p class="mt-1 text-xs">
                    {{ $alreadyHolding->map(fn ($s) => $s->fullName().' ('.$s->class_name.')')->join(', ') }}
                </p>
            </div>
        @endif

        @if ($rows->isEmpty())
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                Nobody in your selection needs a new token.
                <a href="{{ route('result-pins.bulk.create', ['session' => $session, 'term' => $term->value]) }}" class="font-semibold text-primary-600 hover:underline">Choose other students</a>.
            </div>
        @endif

        {{-- Everyone, class by class. --}}
        @foreach ($groups as $className => $students)
            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $className }}</h3>
                    <span class="text-xs font-semibold text-gray-500"><span x-text="remaining(@js($className))">{{ $students->count() }}</span> student(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                <th class="w-12 px-5 py-2 font-semibold">S/N</th>
                                <th class="px-3 py-2 font-semibold">Student</th>
                                <th class="px-3 py-2 font-semibold">Admission No.</th>
                                <th class="px-3 py-2 font-semibold">Class</th>
                                <th class="px-5 py-2 text-right font-semibold"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($students as $student)
                                <tr :class="removed[{{ $student->id }}] ? 'bg-gray-50 opacity-50 dark:bg-gray-900/40' : ''">
                                    <td class="px-5 py-2 text-gray-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white" :class="removed[{{ $student->id }}] && 'line-through'">{{ $student->fullName() }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $student->admission_number ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $student->class_name }}</td>
                                    <td class="px-5 py-2 text-right">
                                        <button type="button" x-show="! removed[{{ $student->id }}]" x-on:click="removed[{{ $student->id }}] = true" class="rounded-[6px] px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Remove
                                        </button>
                                        <button type="button" x-show="removed[{{ $student->id }}]" x-cloak x-on:click="delete removed[{{ $student->id }}]" class="rounded-[6px] px-2 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400">
                                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <form
            method="POST"
            action="{{ route('result-pins.bulk.store') }}"
            class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 rounded-[10px] border border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-800/95 lg:bottom-4"
            x-on:submit="if (total === 0) { $event.preventDefault(); return } $el.querySelector('[type=submit]').disabled = true"
        >
            @csrf
            <input type="hidden" name="session" value="{{ $session }}">
            <input type="hidden" name="term" value="{{ $term->value }}">

            @foreach ($groups as $className => $students)
                @foreach ($students as $student)
                    <input type="hidden" name="student_ids[]" value="{{ $student->id }}" :disabled="removed[{{ $student->id }}]">
                @endforeach
                <input type="hidden" name="quantities[{{ $loop->index }}][class]" value="{{ $className }}">
                <input type="hidden" name="quantities[{{ $loop->index }}][count]" value="{{ $students->count() }}" :value="remaining(@js($className))">
            @endforeach

            <div class="flex flex-wrap items-center gap-3">
                <a :href="backUrl" href="{{ route('result-pins.bulk.create', ['session' => $session, 'term' => $term->value]) }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-arrow-left btn-icon" aria-hidden="true"></i> Change Selection
                </a>
                <small class="block min-w-0 flex-1 text-xs text-gray-500 dark:text-gray-400">
                    One token per student, bound to their own class's {{ $term->label() }} result.
                </small>
                <button type="submit" :disabled="total === 0" class="btn rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fa-solid fa-wand-magic-sparkles btn-icon" aria-hidden="true"></i>
                    Confirm &amp; Generate <span x-text="total">{{ $rows->count() }}</span> Token<span x-show="total !== 1">s</span>
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
