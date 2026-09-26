@php
    $rows = $preview['rows'] ?? [];
    $validCount = collect($rows)->filter(fn ($row) => $row['errors'] === [])->count();
    $invalidCount = count($rows) - $validCount;
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
            <div class="{{ $cardClass }} p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">1. Upload your list</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Upload an Excel (.xlsx), CSV, Word (.docx) or PDF file with one student/pupil per row. You will see everything we read from it before anything is saved.
                    Prefer to add students one at a time? <a href="{{ route('students.index') }}" class="font-semibold text-blue-600 hover:underline">Use Add Student instead</a>.
                </p>

                @if ($classes === [])
                    <div class="mt-4 rounded-[8px] bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        Your school has no classes yet. Create your classes in the Academics section first, then come back to import students/pupils into them.
                    </div>
                @else
                    <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-select-field
                                name="class_name"
                                label="Class"
                                required
                                placeholder="Select a class"
                                helper="Every student/pupil in this file will be added to this class."
                                :options="collect($classes)->mapWithKeys(fn ($name) => [$name => $name])->all()"
                            />

                            <div>
                                <label for="import_file" class="field-label">File<span class="text-red-500"> *</span></label>
                                <input id="import_file" type="file" name="file" required accept=".csv,.txt,.xlsx,.docx,.pdf" class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-300">
                                <p class="field-hint mt-1">CSV, Excel (.xlsx), Word (.docx) or PDF, up to 5MB and {{ number_format(\App\Services\StudentImport\StudentImportParser::MAX_ROWS) }} students/pupils.</p>
                            </div>
                        </div>

                        <fieldset>
                            <legend class="field-label">If full names are in a single "Name" column</legend>
                            <div class="mt-1 flex flex-wrap gap-4 text-sm text-gray-700 dark:text-gray-200">
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="name_order" value="surname_first" @checked(old('name_order', 'surname_first') === 'surname_first')>
                                    Surname first (e.g. "Okafor Chinedu")
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="radio" name="name_order" value="first_name_first" @checked(old('name_order') === 'first_name_first')>
                                    First name first (e.g. "Chinedu Okafor")
                                </label>
                            </div>
                        </fieldset>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <a href="{{ route('students.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</a>
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Read File</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="{{ $cardClass }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">How to lay out the file</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The first row should hold the column headings. We recognise common names for each column, so your existing register will usually work as it is.</p>
                    </div>
                    <a href="{{ route('students.import.template') }}" class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Download Template
                    </a>
                </div>

                <ul class="mt-4 grid grid-cols-1 gap-2 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                    <li><span class="font-semibold text-gray-900 dark:text-white">Required:</span> First Name and Last Name (or Surname), or one Name column; Gender (Male/Female or M/F){{ $school->auto_generate_admission_numbers ? '' : '; Admission Number' }}.</li>
                    <li><span class="font-semibold text-gray-900 dark:text-white">Optional:</span> Date of Birth, House, Guardian Name, Guardian Phone, Guardian Email, Student Phone, Student Email, Address, Admission Date, Notes.</li>
                    <li><span class="font-semibold text-gray-900 dark:text-white">Dates:</span> day first, e.g. 14/03/2014.</li>
                    <li>
                        <span class="font-semibold text-gray-900 dark:text-white">Admission numbers:</span>
                        @if ($school->auto_generate_admission_numbers)
                            generated automatically for each student/pupil, as when adding one by hand.
                        @else
                            taken from the file and must be unique at your school.
                        @endif
                    </li>
                    <li><span class="font-semibold text-gray-900 dark:text-white">Class:</span> chosen above; any class column in the file is ignored.</li>
                    <li><span class="font-semibold text-gray-900 dark:text-white">Afterwards:</span> edit details, add photographs and set or reset login details for each student/pupil as usual.</li>
                </ul>
            </div>
        @else
            {{-- Step 2: review what was read, then confirm --}}
            <div class="{{ $cardClass }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">2. Review and confirm</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            From <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $preview['file_name'] }}</span>, into
                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $preview['class_name'] }}</span>.
                            Nothing has been saved yet.
                        </p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Columns found: {{ implode(', ', $preview['columns']) }}.</p>
                        @if ($preview['ignored'] !== [])
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ignored: {{ implode(', ', $preview['ignored']) }}.</p>
                        @endif
                    </div>
                    <div class="flex gap-3 text-center">
                        <div class="rounded-[8px] bg-green-50 px-4 py-2 dark:bg-green-900/30">
                            <p class="text-2xl font-extrabold text-green-700 dark:text-green-400">{{ number_format($validCount) }}</p>
                            <p class="text-xs font-medium text-green-700 dark:text-green-400">ready to import</p>
                        </div>
                        <div class="rounded-[8px] px-4 py-2 {{ $invalidCount ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                            <p class="text-2xl font-extrabold {{ $invalidCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ number_format($invalidCount) }}</p>
                            <p class="text-xs font-medium {{ $invalidCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">will be skipped</p>
                        </div>
                    </div>
                </div>

                @if ($invalidCount)
                    <p class="mt-4 rounded-[8px] bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        Rows marked in red cannot be imported as they are. You can import the valid rows now and add the others by hand, or fix the file and upload it again.
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <form method="POST" action="{{ route('students.import.cancel', ['token' => $token]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Upload a Different File</button>
                    </form>
                    @if ($validCount)
                        <form method="POST" action="{{ route('students.import.store', ['token' => $token]) }}" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <button type="submit" :disabled="busy" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                <span x-show="!busy">Import {{ number_format($validCount) }} {{ Str::plural('Student', $validCount) }}</span>
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
