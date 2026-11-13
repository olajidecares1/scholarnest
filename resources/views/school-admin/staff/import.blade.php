@php
    use App\Services\StaffImport\StaffRoleResolver;

    $existingCount = collect($rows)->filter(fn ($row) => $row['existing'] !== null)->count();
    $readyRows = collect($rows)->filter(fn ($row) => $row['errors'] === [] && $row['existing'] === null);
    $readyCount = $readyRows->count();
    $errorCount = count($rows) - $readyCount - $existingCount;
    $readyTeachers = $readyRows->filter(fn ($row) => StaffRoleResolver::isTeacher((string) $row['kind']))->count();
    $overTeacherLimit = $teacherRoom !== null ? max(0, $readyTeachers - $teacherRoom) : 0;
    $readyClassTeachers = $readyRows->filter(fn ($row) => $row['class_teacher_of'] !== null)->count();
    $assignRows = collect($rows)->filter(fn ($row) => $row['assign_class'] !== null);
    $assignCount = $assignRows->count();
    $readyNoPhone = $readyRows->filter(fn ($row) => blank($row['data']['phone']))->count();
    $cardClass = 'rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]';
    $chip = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';
    $genderLabel = fn ($value) => $value ? \App\Enums\Gender::from($value)->label() : null;

    // What the edit form starts from: the row as the school typed it, never
    // the password.
    $editable = collect($rows)->mapWithKeys(fn ($row) => [$row['line'] => [
        'line' => $row['line'],
        'first_name' => $row['raw']['first_name'] ?? '',
        'last_name' => $row['raw']['last_name'] ?? '',
        'role' => $row['role_label'] ?? ($row['raw']['role'] ?? ''),
        'class' => $row['raw']['class'] ?? '',
        'class_teacher' => $row['raw']['class_teacher'] ?? '',
        'subjects' => $row['raw']['subjects'] ?? '',
        'gender' => $genderLabel($row['data']['gender']) ?? ($row['raw']['gender'] ?? ''),
        'phone' => $row['raw']['phone'] ?? '',
        'email' => $row['raw']['email'] ?? '',
        'date_of_birth' => $row['data']['date_of_birth'] ? \Carbon\Carbon::parse($row['data']['date_of_birth'])->format('d/m/Y') : ($row['raw']['date_of_birth'] ?? ''),
        'has_password' => $row['password_source'] === 'file',
        'update_url' => $token ? route('staff.import.rows.update', ['token' => $token, 'line' => $row['line']]) : '',
    ]])->all();
@endphp

<x-dashboard-layout page-title="Bulk Register Staff" page-subtitle="Register your whole staff list from one file: accounts, roles, class teachers and WhatsApp login details in one go.">
    <div class="space-y-6" x-data="{ edit: null, rows: @js($editable) }">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Staff
        </a>

        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                <small class="block text-xs leading-relaxed">{{ session('status') }}</small>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li><small class="text-xs leading-relaxed">{{ $error }}</small></li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $whatsAppReady)
            <div class="flex gap-2 rounded-[8px] bg-amber-50 px-4 py-3 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                <i class="fa-brands fa-whatsapp mt-0.5 text-[14px]" aria-hidden="true"></i>
                <small class="block text-xs leading-relaxed">
                    Automatic WhatsApp sending is not set up yet, so login details will not be sent by themselves. After registering, you will get a one-tap WhatsApp button for each staff member instead.
                </small>
            </div>
        @endif

        @if (! $preview)
            {{-- Step 1: the file --}}
            <div class="{{ $cardClass }}">
                <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[11px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">1</span>
                    <div class="min-w-0">
                        <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">Upload your staff list</h3>
                        <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            One staff member per row. You will review every row, and can correct any of them, before anyone is registered.
                            Adding just one? <a href="{{ route('staff.index') }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Use Add Staff</a>.
                        </small>
                    </div>
                </div>

                @if (! $school->school_code)
                    <div class="m-5 flex gap-2 rounded-[8px] bg-amber-50 px-3 py-2.5 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        <i class="fa-solid fa-circle-info mt-0.5 text-[12px]" aria-hidden="true"></i>
                        <small class="block text-xs leading-relaxed">Staff IDs are made from your school code, and none is set yet. Add one in Settings first.</small>
                    </div>
                @endif

                <form method="POST" action="{{ route('staff.import.preview') }}" enctype="multipart/form-data" x-data="{ fileName: '', dragging: false }">
                    @csrf

                    <div class="grid grid-cols-1 gap-5 px-5 py-5 md:grid-cols-2">
                        <fieldset>
                            <legend class="field-label">If full names are in one "Full Name" column</legend>
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach (['first_name_first' => ['First name first', 'Ngozi Okafor'], 'surname_first' => ['Surname first', 'Okafor Ngozi']] as $value => [$title, $example])
                                    <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 transition-colors hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60 dark:border-gray-600 dark:has-[:checked]:bg-blue-900/20">
                                        <input type="radio" name="name_order" value="{{ $value }}" class="h-3.5 w-3.5 text-blue-600" @checked(old('name_order', 'first_name_first') === $value)>
                                        <span class="leading-tight">
                                            <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ $title }}</span>
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">e.g. "{{ $example }}"</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <small class="mt-2 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">Titles such as Mr, Mrs, Dr are left out of the name.</small>
                        </fieldset>

                        <div>
                            <label for="import_file" class="field-label">File<span class="text-red-500"> *</span></label>
                            <div class="relative mt-1.5 flex min-h-[132px] flex-col items-center justify-center rounded-[10px] border-2 border-dashed px-4 py-5 text-center transition-colors"
                                 :class="dragging ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-900/20' : (fileName ? 'border-green-400 bg-green-50/50 dark:border-green-700 dark:bg-green-900/10' : 'border-gray-300 hover:border-blue-400 dark:border-gray-600')"
                                 @dragover="dragging = true" @dragleave="dragging = false" @drop="dragging = false">
                                <input id="import_file" type="file" name="file" required accept=".xlsx,.csv,.txt,.docx,.pdf"
                                       class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                       @change="fileName = $event.target.files[0]?.name || ''">
                                <i class="fa-solid text-[22px]" :class="fileName ? 'fa-file-circle-check text-green-600' : 'fa-file-arrow-up text-blue-500'" aria-hidden="true"></i>
                                <span class="mt-2 text-xs font-medium text-gray-800 dark:text-gray-100" x-show="! fileName">
                                    <span class="text-blue-600 dark:text-blue-400">Choose a file</span> or drag it here
                                </span>
                                <span class="mt-2 max-w-full truncate text-xs font-medium text-gray-800 dark:text-gray-100" x-show="fileName" x-text="fileName" style="display: none;"></span>
                                <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    Excel or CSV (Word and PDF work too) &middot; up to 5MB &middot; {{ number_format(\App\Services\StaffImport\StaffImportParser::MAX_ROWS) }} staff
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                        <a href="{{ route('staff.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel</a>
                        <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                            <i class="fa-solid fa-magnifying-glass text-[11px] leading-none" aria-hidden="true"></i>
                            Read and Check File
                        </button>
                    </div>
                </form>
            </div>

            <div class="{{ $cardClass }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <div class="min-w-0">
                        <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">How to lay out the file</h3>
                        <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">Column headings in the first row. Common names are recognised ("Surname", "Designation", "WhatsApp No."), so your own list usually works as it is.</small>
                    </div>
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-download text-[12px] leading-none" aria-hidden="true"></i>
                            Download Template
                            <i class="fa-solid fa-chevron-down text-[10px] leading-none transition-transform duration-200" :class="open && 'rotate-180'" aria-hidden="true"></i>
                        </button>
                        <div x-cloak x-show="open" role="menu"
                             class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-[10px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            @foreach ([
                                'xlsx' => ['Excel (with drop-downs)', '.xlsx', 'fa-file-excel', 'text-green-600'],
                                'csv' => ['CSV spreadsheet', '.csv', 'fa-file-csv', 'text-emerald-600'],
                                'docx' => ['Word document', '.docx', 'fa-file-word', 'text-blue-600'],
                                'pdf' => ['PDF (print and fill)', '.pdf', 'fa-file-pdf', 'text-red-600'],
                            ] as $format => [$label, $extension, $icon, $colour])
                                <a href="{{ route('staff.import.template', ['format' => $format]) }}" role="menuitem" @click="open = false"
                                   class="flex items-center gap-3 px-3 py-2 text-xs text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    <i class="fa-solid {{ $icon }} {{ $colour }} w-4 text-center text-[14px] leading-none" aria-hidden="true"></i>
                                    <span class="flex-1 font-semibold">{{ $label }}</span>
                                    <small class="text-[11px] text-gray-400">{{ $extension }}</small>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-gray-100 px-5 text-xs dark:divide-gray-700">
                    <div class="grid grid-cols-1 gap-1.5 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Required columns</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach (['Full Name', 'Role', 'Gender'] as $column)
                                <span class="{{ $chip }} bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $column }}</span>
                            @endforeach
                            <small class="block w-full text-xs leading-relaxed text-gray-500 dark:text-gray-400">First Name and Last Name (or Surname) columns work instead of Full Name. Gender can be Male/Female or M/F. A list of teachers can leave out Role: anyone with a class is taken to be a teacher.</small>
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1.5 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Optional columns</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            @foreach (['Class', 'Class Teacher Of', 'Subjects', 'Phone (WhatsApp)', 'Email', 'Date of Birth', 'Password', 'Department', 'Qualification', 'Employment Date', 'Address', 'Notes'] as $column)
                                <span class="{{ $chip }} bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $column }}</span>
                            @endforeach
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1.5 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Roles</dt>
                        <dd>
                            <ul class="space-y-1 text-gray-600 dark:text-gray-300">
                                <li><small class="text-xs leading-relaxed"><span class="font-semibold">Class teachers are found for you</span>: a teacher with one class in the Class column is made its class teacher, and so is anyone whose role says Class Teacher or Form Master (the class can be in the role too, e.g. "Class Teacher, Primary 2"). A "Class Teacher Of" column, or an "Is Class Teacher" Yes/No column, works as well. Teachers already on your staff list are given their class too.</small></li>
                                <li><small class="text-xs leading-relaxed"><span class="font-semibold">Subject Teacher</span>: given the Subjects in the classes listed, e.g. "Mathematics, Physics" in "JSS 1, JSS 2". "Mathematics Teacher" works too.</small></li>
                                <li><small class="text-xs leading-relaxed"><span class="font-semibold">Teacher, Administrative Staff, Management, Support Staff, Other</span>. Everyday titles are understood: Bursar, Secretary, Principal, Vice Principal, Driver, Cleaner and more.</small></li>
                            </ul>
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Your classes</dt>
                        <dd>
                            @if ($classes === [])
                                <small class="block text-xs leading-relaxed text-amber-700 dark:text-amber-300">No classes yet. Create them in Academics before registering class teachers.</small>
                            @else
                                <small class="block text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ implode(', ', $classes) }}</small>
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[150px_1fr] sm:gap-4">
                        <dt class="font-semibold text-gray-800 dark:text-gray-100">Login details</dt>
                        <dd><small class="block text-xs leading-relaxed text-gray-600 dark:text-gray-300">Each person gets a Staff ID automatically. Leave Password blank to have a secure one generated. Login details are sent to the phone number on WhatsApp.</small></dd>
                    </div>
                </dl>
            </div>
        @else
            {{-- Step 2: review, correct, confirm --}}
            <div class="{{ $cardClass }} p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">2. Review, correct and confirm</h3>
                        <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            From <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $preview['file_name'] }}</span>. Nobody has been registered yet.
                            Use <i class="fa-solid fa-pen text-[10px]" aria-hidden="true"></i> to correct a row, <i class="fa-solid fa-trash-can text-[10px]" aria-hidden="true"></i> to leave it out.
                        </small>
                        <small class="mt-2 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">Columns found: {{ implode(', ', $preview['columns']) }}.</small>
                    </div>
                    <div class="flex flex-wrap gap-3 text-center">
                        <div class="rounded-[8px] bg-green-50 px-4 py-2 dark:bg-green-900/30">
                            <p class="text-2xl font-extrabold text-green-700 dark:text-green-400">{{ number_format($readyCount) }}</p>
                            <small class="block text-xs font-medium text-green-700 dark:text-green-400">ready</small>
                        </div>
                        @if ($existingCount)
                            <div class="rounded-[8px] bg-blue-50 px-4 py-2 dark:bg-blue-900/30">
                                <p class="text-2xl font-extrabold text-blue-700 dark:text-blue-400">{{ number_format($existingCount) }}</p>
                                <small class="block text-xs font-medium text-blue-700 dark:text-blue-400">already registered</small>
                            </div>
                        @endif
                        <div class="rounded-[8px] px-4 py-2 {{ $errorCount ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-50 dark:bg-gray-700/50' }}">
                            <p class="text-2xl font-extrabold {{ $errorCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ number_format($errorCount) }}</p>
                            <small class="block text-xs font-medium {{ $errorCount ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">need correcting</small>
                        </div>
                    </div>
                </div>

                @if ($readyCount)
                    <small class="mt-4 block text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                        On confirming: {{ number_format($readyCount) }} {{ Str::plural('account', $readyCount) }} created{{ $readyClassTeachers ? ', '.number_format($readyClassTeachers).' made class '.Str::plural('teacher', $readyClassTeachers).' of their class' : '' }},
                        passwords issued, and login details {{ $whatsAppReady ? 'sent on WhatsApp' : 'ready to share on WhatsApp' }}{{ $readyNoPhone ? ' ('.number_format($readyNoPhone).' without a phone number will not get one)' : '' }}.
                    </small>
                @endif

                @if ($readyClassTeachers || $assignCount)
                    <div class="mt-4 rounded-[8px] border border-blue-100 bg-blue-50/60 p-3 dark:border-blue-900/40 dark:bg-blue-900/20">
                        <small class="block text-xs font-semibold text-blue-800 dark:text-blue-300"><i class="fa-solid fa-chalkboard-user mr-1 text-[11px]" aria-hidden="true"></i> Class teachers found in this list</small>
                        <small class="mt-0.5 block text-xs leading-relaxed text-blue-700/90 dark:text-blue-300/90">Worked out from the file: nobody needs to be assigned by hand.</small>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($readyRows->filter(fn ($row) => $row['class_teacher_of'] !== null)->merge($assignRows)->sortBy(fn ($row) => $row['class_teacher_of'] ?? $row['assign_class']) as $row)
                                <li class="{{ $chip }} bg-white text-gray-700 ring-1 ring-blue-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-blue-800">
                                    <span class="font-semibold text-blue-700 dark:text-blue-300">{{ $row['class_teacher_of'] ?? $row['assign_class'] }}</span>
                                    <span class="mx-1 text-gray-300">&rarr;</span>
                                    {{ trim(($row['data']['first_name'] ?? '').' '.($row['data']['last_name'] ?? '')) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($existingCount)
                    <p class="mt-4 rounded-[8px] bg-blue-50 p-3 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                        <small class="block text-xs leading-relaxed">
                            {{ number_format($existingCount) }} {{ Str::plural('person', $existingCount) }} in this file {{ $existingCount === 1 ? 'is' : 'are' }} already on your staff list, so no new {{ Str::plural('account', $existingCount) }} will be created and their details are left unchanged.
                            @if ($assignCount)
                                {{ number_format($assignCount) }} of them will be made class {{ Str::plural('teacher', $assignCount) }} of the class in the file.
                            @endif
                        </small>
                    </p>
                @endif

                @if ($errorCount)
                    <p class="mt-4 rounded-[8px] bg-amber-50 p-3 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                        <small class="block text-xs leading-relaxed">Rows marked in red will not be registered as they are. Correct them here, or confirm now and they will be listed as not registered, with the reason, on the summary.</small>
                    </p>
                @endif

                @if ($overTeacherLimit > 0)
                    <p class="mt-4 rounded-[8px] bg-amber-50 p-3 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300" role="alert">
                        <small class="block text-xs leading-relaxed">Your plan allows {{ number_format($teacherLimit) }} teacher accounts and {{ number_format($teacherRoom) }} {{ $teacherRoom === 1 ? 'is' : 'are' }} left, so {{ number_format($overTeacherLimit) }} of the teachers in this list will not be registered. Upgrade your subscription to add more teachers.</small>
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <form method="POST" action="{{ route('staff.import.cancel', ['token' => $token]) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><i class="fa-solid fa-arrow-right-arrow-left btn-icon" aria-hidden="true"></i> Upload a Different File</button>
                    </form>
                    @if ($readyCount || $assignCount)
                        <form method="POST" action="{{ route('staff.import.store', ['token' => $token]) }}" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <button type="submit" :disabled="busy" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                <i x-show="!busy" class="fa-solid fa-user-plus btn-icon" aria-hidden="true"></i>
                                <i x-show="busy" style="display: none;" class="fa-solid fa-spinner fa-spin btn-icon" aria-hidden="true"></i>
                                <span x-show="!busy">
                                    @if ($readyCount)
                                        Register {{ number_format($readyCount) }} {{ Str::plural('Staff Member', $readyCount) }}{{ $assignCount ? ' & Assign '.number_format($assignCount).' '.Str::plural('Class Teacher', $assignCount) : '' }}
                                    @else
                                        Assign {{ number_format($assignCount) }} {{ Str::plural('Class Teacher', $assignCount) }}
                                    @endif
                                </span>
                                <span x-show="busy" style="display: none;">Registering and sending&hellip;</span>
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
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Name</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Role</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Class / Subjects</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Gender</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Phone (WhatsApp)</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Email</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Date of Birth</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Password</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($rows as $row)
                                @php $data = $row['data']; @endphp
                                <tr id="row-{{ $row['line'] }}" class="{{ $row['existing'] ? 'bg-blue-50/50 dark:bg-blue-900/10' : ($row['errors'] ? 'bg-red-50/60 dark:bg-red-900/10' : '') }} scroll-mt-24 align-top">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row['line'] }}</td>
                                    <td class="min-w-[15rem] px-4 py-3">
                                        @if ($row['existing'])
                                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Already registered</span>
                                            <ul class="mt-1 space-y-0.5 text-blue-700 dark:text-blue-400">
                                                <li><small class="block text-xs leading-relaxed">{{ $row['existing'] }}</small></li>
                                                @if ($row['assign_class'])
                                                    <li><small class="block text-xs font-semibold leading-relaxed">Will be made class teacher of {{ $row['assign_class'] }}. Nothing else on their record changes.</small></li>
                                                @else
                                                    <li><small class="block text-xs leading-relaxed">Skipped. The existing record is left unchanged.</small></li>
                                                @endif
                                                @foreach ($row['existing_notes'] as $note)<li><small class="block text-xs leading-relaxed">{{ $note }}</small></li>@endforeach
                                            </ul>
                                        @elseif ($row['errors'])
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">Needs correcting</span>
                                            <ul class="mt-1 space-y-0.5 text-red-700 dark:text-red-400">
                                                @foreach ($row['errors'] as $message)<li><small class="block text-xs leading-relaxed">{{ $message }}</small></li>@endforeach
                                            </ul>
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Ready</span>
                                        @endif
                                        @if ($row['warnings'])
                                            <ul class="mt-1 space-y-0.5 text-amber-700 dark:text-amber-400">
                                                @foreach ($row['warnings'] as $message)<li><small class="block text-xs leading-relaxed">{{ $message }}</small></li>@endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">{{ trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $row['role_label'] ?? ($row['raw']['role'] ?? '') }}
                                        @if ($row['role_label'] && isset($row['raw']['role']) && mb_strtolower($row['raw']['role']) !== mb_strtolower($row['role_label']))
                                            <small class="block text-xs text-gray-400">"{{ $row['raw']['role'] }}"</small>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        @if (($row['existing'] === null ? $row['class_teacher_of'] : $row['assign_class']) !== null)
                                            <span class="{{ $chip }} mb-1 whitespace-nowrap bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300" @if ($row['class_teacher_source'] === 'inferred') data-tooltip="Worked out from the file: a teacher with one class" @endif>
                                                <i class="fa-solid fa-chalkboard-user mr-1 text-[10px]" aria-hidden="true"></i>
                                                Class teacher of {{ $row['existing'] === null ? $row['class_teacher_of'] : $row['assign_class'] }}
                                            </span>
                                        @endif
                                        @if ($row['classes'] && ($row['subjects'] || $row['classes'] !== [$row['existing'] === null ? $row['class_teacher_of'] : $row['assign_class']]))
                                            <span class="whitespace-nowrap">{{ implode(', ', $row['classes']) }}</span>
                                        @elseif (isset($row['raw']['class']))
                                            <span class="text-gray-400">{{ $row['raw']['class'] }}</span>
                                        @endif
                                        @if ($row['subjects'])
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', $row['subjects']) }}</small>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $genderLabel($data['gender']) ?? ($row['raw']['gender'] ?? '') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $data['phone'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $data['email'] ?? ($row['raw']['email'] ?? '') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-200">{{ $data['date_of_birth'] ? \Carbon\Carbon::parse($data['date_of_birth'])->format('d/m/Y') : '' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $row['password_source'] === 'file' ? 'From the file' : 'Generated' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" class="icon-btn icon-btn--edit" data-tooltip="Correct this row" aria-label="Correct row {{ $row['line'] }}" @click="edit = { ...rows[{{ $row['line'] }}], generate_password: false }">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>
                                            <form method="POST" action="{{ route('staff.import.rows.destroy', ['token' => $token, 'line' => $row['line']]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="icon-btn icon-btn--delete" data-tooltip="Leave this row out" aria-label="Leave row {{ $row['line'] }} out"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Correct a row --}}
            <div x-show="edit" x-cloak style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="edit = null">
                <div @click.outside="edit = null" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800" role="dialog" aria-modal="true" aria-labelledby="edit-row-title">
                    <template x-if="edit">
                        <form method="POST" :action="edit.update_url" class="space-y-4">
                            @csrf @method('PUT')
                            <div>
                                <h3 id="edit-row-title" class="text-sm font-bold text-gray-900 dark:text-white">Correct row <span x-text="edit.line"></span></h3>
                                <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">The whole list is checked again when you save.</small>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label" for="edit_first_name">First Name</label>
                                    <input id="edit_first_name" type="text" name="first_name" x-model="edit.first_name" class="mt-1 block w-full">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_last_name">Last Name</label>
                                    <input id="edit_last_name" type="text" name="last_name" x-model="edit.last_name" class="mt-1 block w-full">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_role">Role</label>
                                    <input id="edit_role" type="text" name="role" x-model="edit.role" list="staff-import-roles" class="mt-1 block w-full">
                                    <datalist id="staff-import-roles">
                                        @foreach ($roleOptions as $label)<option value="{{ $label }}"></option>@endforeach
                                    </datalist>
                                </div>
                                <div>
                                    <label class="field-label" for="edit_gender">Gender</label>
                                    <select id="edit_gender" name="gender" x-model="edit.gender" class="mt-1 block w-full">
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label" for="edit_class">Class</label>
                                    <input id="edit_class" type="text" name="class" x-model="edit.class" list="staff-import-classes" class="mt-1 block w-full" placeholder="e.g. Primary 1">
                                    <datalist id="staff-import-classes">
                                        @foreach ($classes as $class)<option value="{{ $class }}"></option>@endforeach
                                    </datalist>
                                    <small class="field-hint mt-1">A teacher with one class becomes its class teacher. Several, separated by commas, for a Subject Teacher.</small>
                                </div>
                                <div>
                                    <label class="field-label" for="edit_class_teacher">Class Teacher Of</label>
                                    <input id="edit_class_teacher" type="text" name="class_teacher" x-model="edit.class_teacher" list="staff-import-classes" class="mt-1 block w-full" placeholder="Leave blank to work it out">
                                    <small class="field-hint mt-1">A class, or No to stop them being made a class teacher.</small>
                                </div>
                                <div>
                                    <label class="field-label" for="edit_subjects">Subjects</label>
                                    <input id="edit_subjects" type="text" name="subjects" x-model="edit.subjects" class="mt-1 block w-full" placeholder="e.g. Mathematics, Physics">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_phone">Phone (WhatsApp)</label>
                                    <input id="edit_phone" type="tel" name="phone" x-model="edit.phone" class="mt-1 block w-full">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_email">Email</label>
                                    <input id="edit_email" type="text" name="email" x-model="edit.email" class="mt-1 block w-full">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_dob">Date of Birth</label>
                                    <input id="edit_dob" type="text" name="date_of_birth" x-model="edit.date_of_birth" class="mt-1 block w-full" placeholder="DD/MM/YYYY">
                                </div>
                                <div>
                                    <label class="field-label" for="edit_password">Password</label>
                                    <input id="edit_password" type="text" name="password" autocomplete="off" class="mt-1 block w-full" :disabled="edit.generate_password"
                                           :placeholder="edit.has_password ? 'Leave blank to keep the one from the file' : 'Leave blank to generate one'">
                                    <label x-show="edit.has_password" class="mt-1.5 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" name="generate_password" value="1" x-model="edit.generate_password" class="h-3.5 w-3.5">
                                        Generate a password instead
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <button type="button" @click="edit = null" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel</button>
                                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-check btn-icon" aria-hidden="true"></i> Save and Re-check</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        @endif
    </div>
</x-dashboard-layout>
