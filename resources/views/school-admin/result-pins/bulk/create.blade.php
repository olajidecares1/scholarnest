<x-dashboard-layout
    page-title="Bulk Exam Tokens"
    page-subtitle="Choose the classes, tick the students who should receive a token, review your selection, then generate and print."
>
    {{-- Step 1 of 3: choose.

         Everything here is a convenience. What is sent on is a list of
         student ids and a number per class; the server re-checks that every
         id is an active student of this school, works out each student's
         class and examination itself, and refuses a number that does not
         match the ticks. See ResultTokenBulkController. --}}
    <div
        class="space-y-6"
        x-data="{
            classes: @js($classes),
            open: {},
            picked: {},
            search: {},

            init() {
                const wanted = new Set(@js($selected->all()));

                for (const c of this.classes) {
                    for (const s of c.students) {
                        if (wanted.has(s.id) && ! s.has_token) {
                            this.picked[s.id] = true;
                            this.open[c.name] = true;
                        }
                    }
                }
            },

            eligible(c) { return c.students.filter((s) => ! s.has_token) },
            count(c) { return c.students.filter((s) => this.picked[s.id]).length },
            allPicked(c) { const e = this.eligible(c); return e.length > 0 && e.every((s) => this.picked[s.id]) },
            visible(c) {
                const q = (this.search[c.name] ?? '').trim().toLowerCase();

                return q === '' ? c.students : c.students.filter((s) => s.name.toLowerCase().includes(q) || String(s.admission_number ?? '').toLowerCase().includes(q));
            },

            toggleClass(c) {
                this.open[c.name] = ! this.open[c.name];

                if (! this.open[c.name]) {
                    c.students.forEach((s) => delete this.picked[s.id]);
                }
            },

            toggle(s) {
                if (s.has_token) return;
                this.picked[s.id] ? delete this.picked[s.id] : (this.picked[s.id] = true);
            },

            selectAll(c, on) {
                this.eligible(c).forEach((s) => on ? (this.picked[s.id] = true) : delete this.picked[s.id]);
            },

            {{-- The number box and the ticks are one thing. Raising the
                 number ticks the next students down the list; lowering it
                 unticks from the bottom; ticking by hand changes the number.
                 So the two can never disagree. --}}
            setQuantity(c, value) {
                const eligible = this.eligible(c);
                const wanted = Math.max(0, Math.min(eligible.length, parseInt(value, 10) || 0));
                let current = this.count(c);

                for (const s of eligible) {
                    if (current >= wanted) break;
                    if (! this.picked[s.id]) { this.picked[s.id] = true; current++; }
                }

                for (const s of [...eligible].reverse()) {
                    if (current <= wanted) break;
                    if (this.picked[s.id]) { delete this.picked[s.id]; current--; }
                }
            },

            get pickedIds() { return Object.keys(this.picked).map(Number) },
            get total() { return this.pickedIds.length },
            get chosen() { return this.classes.filter((c) => this.open[c.name]) },
            get summary() { return this.chosen.map((c) => ({ name: c.name, count: this.count(c) })).filter((c) => c.count > 0) },
        }"
    >
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

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
            <span class="rounded-full bg-primary-600 px-3 py-1 text-white">1. Choose students</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">2. Review</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">3. Generate &amp; print</span>
            <a href="{{ route('result-pins.index') }}" class="ml-auto text-primary-600 hover:underline dark:text-primary-400">
                <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i> Back to Exam Tokens
            </a>
        </div>

        {{-- Year and term. Changing either reloads the lists, because who
             already holds a token depends on the term; the ticks are carried
             across. --}}
        <form method="GET" action="{{ route('result-pins.bulk.create') }}" x-ref="termForm" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Which examination?</h2>
            <small class="field-hint mt-0.5">Each student's token opens their own class's result for this term.</small>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="session" class="field-label mb-1">Academic Year</label>
                    <select id="session" name="session" class="w-full" x-on:change="$refs.termForm.submit()">
                        @foreach ($sessions as $option)
                            <option value="{{ $option }}" @selected($option === $session)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="term" class="field-label mb-1">Term</label>
                    <select id="term" name="term" class="w-full" x-on:change="$refs.termForm.submit()">
                        @foreach ($terms as $option)
                            <option value="{{ $option->value }}" @selected($option === $term)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <template x-if="total <= 400">
                <div>
                    <template x-for="id in pickedIds" :key="id">
                        <input type="hidden" name="selected[]" :value="id">
                    </template>
                </div>
            </template>
        </form>

        {{-- Classes. Any number of them. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Choose classes</h2>
            <small class="field-hint mt-0.5">Select one or more classes. Each one opens its student list below.</small>

            @if ($classes->isEmpty())
                <small class="mt-4 block rounded-[8px] bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                    There are no active students in any class yet. Add students first, then come back to generate their tokens.
                </small>
            @else
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    <template x-for="c in classes" :key="c.name">
                        <label
                            class="flex cursor-pointer items-start gap-2 rounded-[8px] border px-3 py-2.5 text-sm transition"
                            :class="open[c.name] ? 'border-primary-400 bg-primary-50 dark:border-primary-600 dark:bg-primary-900/30' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'"
                        >
                            <input type="checkbox" class="mt-0.5" :checked="open[c.name]" x-on:change="toggleClass(c)">
                            <span class="min-w-0">
                                <span class="block font-semibold text-gray-900 dark:text-white" x-text="c.name"></span>
                                <span class="block text-[11px] text-gray-500 dark:text-gray-400">
                                    <span x-text="c.students.length"></span> student<span x-show="c.students.length !== 1">s</span>
                                    <template x-if="c.students.length !== eligible(c).length">
                                        <span>&middot; <span x-text="c.students.length - eligible(c).length"></span> have a token</span>
                                    </template>
                                </span>
                            </span>
                        </label>
                    </template>
                </div>
            @endif
        </div>

        {{-- One panel per chosen class. --}}
        <template x-for="c in chosen" :key="c.name">
            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-gray-100 p-5 dark:border-gray-700">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="c.name"></h3>
                        <small class="field-hint">
                            <span x-text="count(c)"></span> of <span x-text="eligible(c).length"></span> available student(s) selected
                        </small>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        <input type="checkbox" :checked="allPicked(c)" :disabled="eligible(c).length === 0" x-on:change="selectAll(c, $event.target.checked)">
                        Select All
                    </label>

                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Number of students
                        <input
                            type="number"
                            min="0"
                            :max="eligible(c).length"
                            class="w-20 py-1.5 text-center"
                            :value="count(c)"
                            x-on:input.debounce.400ms="setQuantity(c, $event.target.value); $event.target.value = count(c)"
                            :aria-label="'Number of students in ' + c.name"
                        >
                    </label>
                </div>

                <div class="p-5">
                    <template x-if="c.students.length > 12">
                        <input type="search" class="mb-3 w-full sm:w-72" placeholder="Search by name or admission number" x-model="search[c.name]">
                    </template>

                    <div class="max-h-[28rem] overflow-auto rounded-[8px] border border-gray-100 dark:border-gray-700">
                        <table class="min-w-full text-left text-sm">
                            <thead class="sticky top-0 bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                <tr>
                                    <th class="w-10 px-3 py-2"><span class="sr-only">Select</span></th>
                                    <th class="w-12 px-3 py-2 font-semibold">S/N</th>
                                    <th class="px-3 py-2 font-semibold">Student</th>
                                    <th class="px-3 py-2 font-semibold">Admission No.</th>
                                    <th class="px-3 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template x-for="(s, index) in visible(c)" :key="s.id">
                                    <tr
                                        class="transition"
                                        :class="s.has_token ? 'opacity-60' : (picked[s.id] ? 'cursor-pointer bg-primary-50/60 dark:bg-primary-900/20' : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40')"
                                        x-on:click="if ($event.target.tagName !== 'INPUT') toggle(s)"
                                    >
                                        <td class="px-3 py-2">
                                            <input type="checkbox" :checked="!! picked[s.id]" :disabled="s.has_token" x-on:change="toggle(s)" :aria-label="'Select ' + s.name">
                                        </td>
                                        <td class="px-3 py-2 text-gray-500" x-text="c.students.indexOf(s) + 1"></td>
                                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white" x-text="s.name"></td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-300" x-text="s.admission_number ?? '—'"></td>
                                        <td class="px-3 py-2">
                                            <span x-show="s.has_token" class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">Already has a valid token</span>
                                            <span x-show="! s.has_token" class="text-[11px] text-gray-400">No token yet</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        {{-- Always in view: the running total and the way on. --}}
        <form
            method="POST"
            action="{{ route('result-pins.bulk.review') }}"
            class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 rounded-[10px] border border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-800/95 lg:bottom-4"
        >
            @csrf
            <input type="hidden" name="session" value="{{ $session }}">
            <input type="hidden" name="term" value="{{ $term->value }}">

            <template x-for="id in pickedIds" :key="id">
                <input type="hidden" name="student_ids[]" :value="id">
            </template>
            <template x-for="(row, i) in summary" :key="row.name">
                <span>
                    <input type="hidden" :name="'quantities[' + i + '][class]'" :value="row.name">
                    <input type="hidden" :name="'quantities[' + i + '][count]'" :value="row.count">
                </span>
            </template>

            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                        <span x-text="total"></span> student<span x-show="total !== 1">s</span> selected
                        <span class="font-medium text-gray-500">&middot; {{ $term->label() }}, {{ $session }}</span>
                    </p>
                    <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">
                        <template x-for="row in summary" :key="row.name">
                            <span class="mr-3"><span x-text="row.name"></span>: <strong x-text="row.count"></strong></span>
                        </template>
                        <span x-show="summary.length === 0">Choose a class, then tick the students.</span>
                    </p>
                </div>
                <button type="submit" :disabled="total === 0" class="btn rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Review Selection <i class="fa-solid fa-arrow-right btn-icon" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    </div>
</x-dashboard-layout>
