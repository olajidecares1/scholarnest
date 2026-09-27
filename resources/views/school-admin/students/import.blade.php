@php
    $rows = $preview['rows'] ?? [];
    $validCount = collect($rows)->filter(fn ($row) => $row['errors'] === [])->count();
    $invalidCount = count($rows) - $validCount;
    // Capped plans import in file order until the allocation is full.
    $importCount = ($capacity ?? null) ? min($validCount, (int) $capacity['remaining']) : $validCount;
    $overLimit = $validCount - $importCount;
    $fields = \App\Services\StudentImport\StudentImportParser::FIELDS;
    $shownFields = collect($fields)->filter(fn ($label, $field) => collect($rows)->contains(fn ($row) => filled($row['data'][$field] ?? null)))->all();
    $cardClass = 'rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]';
@endphp

<x-dashboard-layout page-title="Bulk Upload Students" page-subtitle="Add a whole class of students/pupils from one file.">
    <div class="space-y-6">
        <a href="{{ route('students.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Students
        </a>

        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($capacity)
            <x-student-capacity-card :capacity="$capacity" />
        @endif

        @if (! $preview)
            {{-- Step 1: choose the class and the file --}}
            <div class="{{ $cardClass }}">
                <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[11px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">1</span>
                    <div class="min-w-0">
                        <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">Upload your list</h3>
                        <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            One student/pupil per row. You will see everything we read before anything is saved.
                            Adding just one? <a href="{{ route('students.index') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Use Add Student</a>.
                        </small>
                    </div>
                </div>

                @if ($classes === [])
                    <div class="m-5 flex gap-2 rounded-[8px] bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        <i class="fa-solid fa-circle-info mt-0.5 text-[12px]" aria-hidden="true"></i>
                        <span>Your school has no classes yet. Create your classes in the Academics section first, then come back to import students/pupils into them.</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data"
                          x-data="{ fileName: '', dragging: false }">
                        @csrf

                        <div class="grid grid-cols-1 gap-5 px-5 py-5 md:grid-cols-2">
                            <div class="space-y-5">
                                <x-select-field
                                    name="class_name"
                                    label="Class"
                                    required
                                    placeholder="Select a class"
                                    helper="Every student/pupil in this file goes into this class."
                                    :options="collect($classes)->mapWithKeys(fn ($name) => [$name => $name])->all()"
                                />

                                <fieldset>
                                    <legend class="field-label">If full names are in one "Name" column</legend>
                                    <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach (['surname_first' => ['Surname first', 'Okafor Chinedu'], 'first_name_first' => ['First name first', 'Chinedu Okafor']] as $value => [$title, $example])
                                            <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 transition-colors hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60 dark:border-gray-600 dark:has-[:checked]:bg-blue-900/20">
                                                <input type="radio" name="name_order" value="{{ $value }}" class="h-3.5 w-3.5 text-blue-600" @checked(old('name_order', 'surname_first') === $value)>
                                                <span class="leading-tight">
                                                    <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ $title }}</span>
                                                    <small class="block text-[11px] text-gray-500 dark:text-gray-400">e.g. "{{ $example }}"</small>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>

                            <div>
                                <label for="import_file" class="field-label">File<span class="text-red-500"> *</span></label>
                                <div class="relative mt-1.5 flex min-h-[132px] flex-col items-center justify-center rounded-[10px] border-2 border-dashed px-4 py-5 text-center transition-colors"
                                     :class="dragging ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-900/20' : (fileName ? 'border-green-400 bg-green-50/50 dark:border-green-700 dark:bg-green-900/10' : 'border-gray-300 hover:border-blue-400 dark:border-gray-600')"
                                     @dragover="dragging = true" @dragleave="dragging = false" @drop="dragging = false">
                                    <input id="import_file" type="file" name="file" required accept=".csv,.txt,.xlsx,.docx,.pdf"
                                           class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                           @change="fileName = $event.target.files[0]?.name || ''">
                                    <i class="fa-solid text-[22px]" :class="fileName ? 'fa-file-circle-check text-green-600' : 'fa-file-arrow-up text-blue-500'" aria-hidden="true"></i>
                                    <span class="mt-2 text-xs font-medium text-gray-800 dark:text-gray-100" x-show="! fileName">
                                        <span class="text-blue-600 dark:text-blue-400">Choose a file</span> or drag it here
                                    </span>
                                    <span class="mt-2 max-w-full truncate text-xs font-medium text-gray-800 dark:text-gray-100" x-show="fileName" x-text="fileName" style="display: none;"></span>
                                    <small class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">
                                        Excel, CSV, Word or PDF &middot; up to 5MB &middot; {{ number_format(\App\Services\StudentImport\StudentImportParser::MAX_ROWS) }} students/pupils
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                            <a href="{{ route('students.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</a>
                            <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                <i class="fa-solid fa-magnifying-glass text-[11px] leading-none" aria-hidden="true"></i>
                                Read File
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            @php
                $required = ['First Name', 'Last Name / Surname', 'Gender'];
                if (! $school->auto_generate_admission_numbers) {
                    $required[] = 'Admission Number';
                }
                $optional = ['Date of Birth', 'House', 'Guardian Name', 'Guardian Phone', 'Guardian Email', 'Student Phone', 'Student Email', 'Address', 'Admission Date', 'Notes'];
                $chip = 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium';
            @endphp

            <div class="{{ $cardClass }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <div class="min-w-0">
                        <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">How to lay out the file</h3>
                        <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Put column headings in the first row. Common names are recognised, so your existing register usually works as it is.</small>
                    </div>
                    <a href="{{ route('students.import.template') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-download text-[12px] leading-none" aria-hidden="true"></i>
                        Download Template
                    </a>
                </div>

                <dl class="divide-y divide-gray-100 px-5 text-xs dark:divide-gray-700">
                    <div class="grid grid-cols-1 gap-1.5 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Required columns</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach ($required as $column)
                                <span class="{{ $chip }} bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $column }}</span>
                            @endforeach
                            <small class="w-full text-[11px] text-gray-500 dark:text-gray-400">A single Name column works instead of First and Last Name. Gender can be Male/Female or M/F.</small>
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1.5 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Optional columns</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach ($optional as $column)
                                <span class="{{ $chip }} bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $column }}</span>
                            @endforeach
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Dates</dt>
                        <dd class="text-gray-600 dark:text-gray-300">Day first, e.g. 14/03/2014.</dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Admission numbers</dt>
                        <dd class="text-gray-600 dark:text-gray-300">
                            @if ($school->auto_generate_admission_numbers)
                                Generated automatically for each student/pupil, as when adding one by hand.
                            @else
                                Taken from the file and must be unique at your school.
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Class</dt>
                        <dd class="text-gray-600 dark:text-gray-300">Chosen above. Any class column in the file is ignored.</dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[140px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Afterwards</dt>
                        <dd class="text-gray-600 dark:text-gray-300">Edit details, add photographs and set login details for each student/pupil as usual.</dd>
                    </div>
                </dl>
            </div>
        @else
            {{-- Step 2: review what was read, then confirm --}}
            <div class="{{ $cardClass }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">2. Review and confirm</h3>
                        <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">
                            From <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $preview['file_name'] }}</span>, into
                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $preview['class_name'] }}</span>.
                            Nothing has been saved yet.
                        </small>
                        <small class="block mt-2 text-xs text-gray-500 dark:text-gray-400">Columns found: {{ implode(', ', $preview['columns']) }}.</small>
                        @if ($preview['ignored'] !== [])
                            <small class="block mt-1 text-xs text-gray-500 dark:text-gray-400">Ignored: {{ implode(', ', $preview['ignored']) }}.</small>
                        @endif
                    </div>
                    <div class="flex gap-3 text-center">
                        <div class="rounded-[8px] bg-green-50 px-4 py-2 dark:bg-green-900/30">
                            <p class="text-2xl font-extrabold text-green-700 dark:text-green-400">{{ number_format($validCount) }}</p>
                            <p class="text-xs font-medium text-green-700 dark:text-green-400">ready to import</p>
                        </div>
                        <div class="rounded-[8px] px-4 py-2 {{ $invalidCount ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                            <p class="text-2xl font-extrabold {{ $invalidCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ number_format($invalidCount) }}</p>
                            <small class="block text-xs font-medium {{ $invalidCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">will be skipped</small>
                        </div>
                    </div>
                </div>

                @if ($invalidCount)
                    <p class="mt-4 rounded-[8px] bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        Rows marked in red cannot be imported as they are. You can import the valid rows now and add the others by hand, or fix the file and upload it again.
                    </p>
                @endif

                @if ($overLimit > 0)
                    <p class="mt-4 rounded-[8px] bg-amber-50 p-3 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300" role="alert">
                        <small class="block text-sm">
                            @if ($importCount > 0)
                                Your school subscription allows a maximum of {{ number_format($capacity['allocated']) }} students and {{ number_format($capacity['remaining']) }} {{ Str::plural('space', $capacity['remaining']) }} {{ $capacity['remaining'] === 1 ? 'is' : 'are' }} left.
                                Only the first {{ number_format($importCount) }} students in this list will be added; the other {{ number_format($overLimit) }} will not. Please subscribe for additional student capacity.
                            @else
                                Your school subscription allows a maximum of {{ number_format($capacity['allocated']) }} students and all of them are in use, so none of this list can be added. Please subscribe for additional student capacity.
                            @endif
                        </small>
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <form method="POST" action="{{ route('students.import.cancel', ['token' => $token]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Upload a Different File</button>
                    </form>
                    @if ($importCount)
                        <form method="POST" action="{{ route('students.import.store', ['token' => $token]) }}" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <button type="submit" :disabled="busy" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                <span x-show="!busy">Import {{ number_format($importCount) }} {{ Str::plural('Student', $importCount) }}</span>
                                <span x-show="busy" style="display: none;">Importing&hellip;</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="{{ $cardClass }}">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Row</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                @foreach ($shownFields as $label)
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($rows as $row)
                                <tr class="{{ $row['errors'] ? 'bg-red-50/60 dark:bg-red-900/10' : '' }} align-top">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row['line'] }}</td>
                                    <td class="min-w-[14rem] px-4 py-3">
                                        @if ($row['errors'])
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Skipped</span>
                                            <ul class="mt-1 space-y-0.5 text-xs text-red-700 dark:text-red-400">
                                                @foreach ($row['errors'] as $message)<li>{{ $message }}</li>@endforeach
                                            </ul>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Ready</span>
                                        @endif
                                        @if ($row['warnings'])
                                            <ul class="mt-1 space-y-0.5 text-xs text-amber-700 dark:text-amber-400">
                                                @foreach ($row['warnings'] as $message)<li>{{ $message }}</li>@endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    @foreach ($shownFields as $field => $label)
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">
                                            @php $value = $row['data'][$field] ?? null; @endphp
                                            @if ($field === 'gender' && $value)
                                                {{ \App\Enums\Gender::from($value)->label() }}
                                            @elseif (in_array($field, ['date_of_birth', 'admission_date'], true) && $value)
                                                {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                                            @else
                                                {{ $value ?? '' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
