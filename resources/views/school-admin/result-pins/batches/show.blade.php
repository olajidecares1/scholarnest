@php
    $tokens = $groups->flatten(1);
@endphp

<x-dashboard-layout
    page-title="Exam Token Batch"
    page-subtitle="{{ $batch->term->label() }}, {{ $batch->session }} · generated {{ $batch->created_at->format('j M Y, g:i a') }}{{ $batch->generatedBy ? ' by '.$batch->generatedBy->name : '' }}"
>
    {{-- Step 3 of 3: the batch, and printing it.

         Every print button posts to one action with this batch's id, and
         optionally a class or the chosen tokens. The PDF is built from the
         tokens this batch created, so it is always exactly what was
         generated here. --}}
    <div
        class="space-y-6"
        x-data="{
            chosen: {},
            all: @js($tokens->pluck('uuid')->values()),
            get chosenIds() { return Object.keys(this.chosen) },
            get allChosen() { return this.all.length > 0 && this.all.every((id) => this.chosen[id]) },
            toggleAll(on) { this.all.forEach((id) => on ? (this.chosen[id] = true) : delete this.chosen[id]) },
            toggleClass(ids, on) { ids.forEach((id) => on ? (this.chosen[id] = true) : delete this.chosen[id]) },
        }"
    >
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">1. Choose students</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-500 dark:bg-gray-700 dark:text-gray-300">2. Review</span>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-400" aria-hidden="true"></i>
            <span class="rounded-full bg-primary-600 px-3 py-1 text-white">3. Generate &amp; print</span>
            <span class="ml-auto flex gap-3">
                <a href="{{ route('result-pins.bulk.create', ['session' => $batch->session, 'term' => $batch->term->value]) }}" class="text-primary-600 hover:underline dark:text-primary-400">
                    <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i> New batch
                </a>
                <a href="{{ route('result-pins.index') }}" class="text-primary-600 hover:underline dark:text-primary-400">
                    <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i> Exam Tokens
                </a>
            </span>
        </div>

        {{-- Totals --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Tokens</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $tokens->count() }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Classes</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $groups->count() }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">A4 pages</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ (int) ceil($tokens->count() / \App\Http\Controllers\SchoolAdmin\ResultTokenBulkController::ROWS_PER_PAGE) }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Term</p>
                <p class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $batch->term->label() }}<br><span class="font-medium text-gray-500">{{ $batch->session }}</span></p>
            </div>
        </div>

        @if ($withdrawn->isNotEmpty())
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300">
                {{ $withdrawn->count() }} token(s) from this batch have since been revoked or have expired, so they are left out of the list and the printout:
                {{ $withdrawn->map(fn ($pin) => $pin->boundStudent?->fullName())->filter()->join(', ') }}.
            </div>
        @endif

        {{-- Print options. Each opens the A4 PDF in a new tab, ready to print,
             or downloads it. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Print tokens</h2>
            <small class="field-hint mt-0.5">A4, {{ \App\Http\Controllers\SchoolAdmin\ResultTokenBulkController::ROWS_PER_PAGE }} students per page: name, admission number, class and token.</small>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                {{-- Everything --}}
                <div class="rounded-[8px] border border-gray-100 p-4 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">All tokens in this batch</p>
                    <small class="mt-0.5 block text-xs text-gray-500">{{ $tokens->count() }} student(s)</small>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ([false, true] as $download)
                            <form method="POST" action="{{ route('result-pins.print') }}" @unless ($download) target="_blank" @endunless>
                                @csrf
                                <input type="hidden" name="batch" value="{{ $batch->uuid }}">
                                <input type="hidden" name="download" value="{{ $download ? 1 : 0 }}">
                                <button type="submit" class="btn rounded-[8px] {{ $download ? 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-200' : 'bg-primary-600 text-white hover:bg-primary-700' }} px-3 py-2 text-xs font-semibold">
                                    <i class="fa-solid {{ $download ? 'fa-file-arrow-down' : 'fa-print' }} btn-icon" aria-hidden="true"></i> {{ $download ? 'Download PDF' : 'Print All' }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- One class --}}
                <form method="POST" action="{{ route('result-pins.print') }}" target="_blank" x-data="{ download: 0 }" class="rounded-[8px] border border-gray-100 p-4 dark:border-gray-700">
                    @csrf
                    <input type="hidden" name="batch" value="{{ $batch->uuid }}">
                    <input type="hidden" name="download" :value="download">
                    <label for="print_class" class="text-sm font-semibold text-gray-900 dark:text-white">One class</label>
                    <select id="print_class" name="class_name" required class="mt-2 w-full">
                        @foreach ($groups as $className => $classTokens)
                            <option value="{{ $className }}">{{ $className }} ({{ $classTokens->count() }})</option>
                        @endforeach
                    </select>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="submit" x-on:click="download = 0" class="btn rounded-[8px] bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700">
                            <i class="fa-solid fa-print btn-icon" aria-hidden="true"></i> Print Class
                        </button>
                        <button type="submit" x-on:click="download = 1" class="btn rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <i class="fa-solid fa-file-arrow-down btn-icon" aria-hidden="true"></i> Download PDF
                        </button>
                    </div>
                </form>

                {{-- Ticked students --}}
                <form method="POST" action="{{ route('result-pins.print') }}" target="_blank" x-data="{ download: 0 }" class="rounded-[8px] border border-gray-100 p-4 dark:border-gray-700">
                    @csrf
                    <input type="hidden" name="batch" value="{{ $batch->uuid }}">
                    <input type="hidden" name="download" :value="download">
                    <template x-for="id in chosenIds" :key="id">
                        <input type="hidden" name="tokens[]" :value="id">
                    </template>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Selected students only</p>
                    <small class="mt-0.5 block text-xs text-gray-500"><span x-text="chosenIds.length">0</span> ticked in the list below</small>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="submit" x-on:click="download = 0" :disabled="chosenIds.length === 0" class="btn rounded-[8px] bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <i class="fa-solid fa-print btn-icon" aria-hidden="true"></i> Print Selected
                        </button>
                        <button type="submit" x-on:click="download = 1" :disabled="chosenIds.length === 0" class="btn rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200">
                            <i class="fa-solid fa-file-arrow-down btn-icon" aria-hidden="true"></i> Download PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Send to parents and guardians: the whole batch, one class, or the
             ticked students, by email, WhatsApp or both, after a
             confirmation that says who has no contact details. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Send to parents/guardians</h2>
                    <small class="field-hint mt-0.5">Each parent gets their own child&rsquo;s result link, token and admission number, by email, WhatsApp or both.</small>
                </div>
                <a href="{{ route('result-pins.details.history') }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Sending history
                </a>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-send-result-details :action="route('result-pins.details.preview')" label="Send All">
                    <input type="hidden" name="batch" value="{{ $batch->uuid }}">
                </x-send-result-details>

                <div class="flex items-center gap-2" x-data="{ sendClass: @js($groups->keys()->first()) }">
                    <select x-model="sendClass" class="w-44" aria-label="Class to send">
                        @foreach ($groups as $className => $classTokens)
                            <option value="{{ $className }}">{{ $className }} ({{ $classTokens->count() }})</option>
                        @endforeach
                    </select>
                    <x-send-result-details :action="route('result-pins.details.preview')" label="Send Class">
                        <input type="hidden" name="batch" value="{{ $batch->uuid }}">
                        <input type="hidden" name="class_name" :value="sendClass">
                    </x-send-result-details>
                </div>

                <x-send-result-details :action="route('result-pins.details.preview')" label="Send Selected" disabled-when="chosenIds.length === 0">
                    <template x-for="id in chosenIds" :key="id">
                        <input type="hidden" name="tokens[]" :value="id">
                    </template>
                </x-send-result-details>
            </div>
        </div>

        {{-- The tokens themselves, exactly as they print. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Generated tokens</h2>
                <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                    <input type="checkbox" :checked="allChosen" x-on:change="toggleAll($event.target.checked)"> Select All
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-5 py-2"><span class="sr-only">Select</span></th>
                            <th class="w-12 px-3 py-2 font-semibold">S/N</th>
                            <th class="px-3 py-2 font-semibold">Student</th>
                            <th class="px-3 py-2 font-semibold">Admission No.</th>
                            <th class="px-3 py-2 font-semibold">Class</th>
                            <th class="px-5 py-2 font-semibold">Exam Token</th>
                        </tr>
                    </thead>
                    @php $serial = 0; @endphp
                    @foreach ($groups as $className => $classTokens)
                        <tbody class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                            <tr class="bg-gray-50/60 dark:bg-gray-900/30">
                                <td class="px-5 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="@js($classTokens->pluck('uuid')->values()).every((id) => chosen[id])"
                                        x-on:change="toggleClass(@js($classTokens->pluck('uuid')->values()), $event.target.checked)"
                                        aria-label="Select all in {{ $className }}"
                                    >
                                </td>
                                <td colspan="5" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ $className }} &middot; {{ $classTokens->count() }}</td>
                            </tr>
                            @foreach ($classTokens as $pin)
                                @php $serial++; @endphp
                                <tr>
                                    <td class="px-5 py-2">
                                        <input type="checkbox" :checked="!! chosen[@js($pin->uuid)]" x-on:change="$event.target.checked ? (chosen[@js($pin->uuid)] = true) : delete chosen[@js($pin->uuid)]" aria-label="Select {{ $pin->boundStudent->fullName() }}">
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $serial }}</td>
                                    <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ $pin->boundStudent->fullName() }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $pin->boundStudent->admission_number ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $className }}</td>
                                    <td class="px-5 py-2 font-mono text-sm font-bold tracking-wider text-gray-900 dark:text-white">{{ $pin->plainToken() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
