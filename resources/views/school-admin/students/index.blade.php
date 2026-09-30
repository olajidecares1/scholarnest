@php
    $genderOptions = \App\Enums\Gender::cases();
@endphp

<x-dashboard-layout page-title="Students" page-subtitle="Manage your school's student records.">
    <div class="space-y-6" x-data="{
        open: false,
        editing: null,
        loginUsernameOverride: null,

        // The Login Details card and the Admission Number field are two views
        // of one column.
        get loginUsername() {
            if (this.loginUsernameOverride !== null) {
                return this.loginUsernameOverride
            }

            return this.editing ? this.editing.admission_number : this.nextAdmissionNumberPreview(this.newClassName)
        },
        set loginUsername(value) {
            this.loginUsernameOverride = value

            if (this.editing) {
                this.editing.admission_number = value
            }
        },
        newClassName: '',
        schoolCode: @js($school->school_code),
        currentSession: @js($school->current_session),
        nextAdmissionSequence: @js($nextAdmissionSequence),
        levelCodesByClassName: @js($levelCodesByClassName),
        nextAdmissionNumberPreview(className) {
            if (! this.schoolCode) {
                return '';
            }

            const levelCode = this.levelCodesByClassName[className] || 'GEN';
            const sequence = String(this.nextAdmissionSequence).padStart(3, '0');

            return `${this.schoolCode}-${this.currentSession || 'NOSESSION'}-${levelCode}-${sequence}`;
        },
    }">
        <x-duplicate-student-notice :notices="session('duplicate_students', [])" />

        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if (session('capacity_notice'))
            <div class="rounded-[5px] bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 lg:rounded-[10px]" role="alert">
                <small class="block text-sm font-medium">{{ session('capacity_notice') }}</small>
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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600">Total Students</p>
                    <x-stat-tooltip text="Every student on record at your school, including inactive ones." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-green-600">Active Students</p>
                    <x-stat-tooltip text="Students currently marked active. Inactive students are excluded from attendance, exams, and class lists." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($activeCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        @input.debounce.500ms="$event.target.form.submit()"
                        placeholder="Search by name or admission number..."
                        class="w-64"
                    >
                    <div
                        class="w-48"
                        x-data="{ classFilter: @js(request('class', '')) }"
                        x-init="$watch('classFilter', () => $el.closest('form').submit())"
                    >
                        <x-select-field
                            name="class"
                            model="classFilter"
                            placeholder="All Classes"
                            :options="['' => 'All Classes'] + $academicLevels->flatMap->classes->pluck('name', 'name')->all()"
                        />
                    </div>
                    @if (request('per_page'))
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                    @endif
                    <button type="submit" class="icon-btn icon-btn--neutral" data-tooltip="Search" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                    @if (request('search') || request('class'))
                        <a href="{{ route('students.index') }}" class="icon-btn icon-btn--neutral" data-tooltip="Clear filters" aria-label="Clear filters"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                    @endif
                </form>

                <div class="flex flex-wrap items-center gap-2">
                {{-- Optional: a whole class from one file. Adding one at a
                     time below stays exactly as it was. --}}
                <a
                    href="{{ route('students.import.create') }}"
                    class="btn flex items-center gap-2 rounded-[8px] border border-blue-600 px-4 py-2 text-sm font-semibold text-blue-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50 hover:shadow-md dark:hover:bg-blue-900/20"
                >
                    <i class="fa-solid fa-upload text-[14px] leading-none" aria-hidden="true"></i>
                    Bulk Upload
                </a>
                <button
                    type="button"
                    @click="editing = null; newClassName = ''; open = true"
                    class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                    Add Student
                </button>
                </div>
            </div>

            {{-- Several students deleted at once: tick them, then "Delete
                 selected". The single delete on each row still works. --}}
            <div
                x-data="{
                    selected: [],
                    names: @js($students->mapWithKeys(fn ($s) => [$s->uuid => $s->fullName()])),
                    confirming: false,
                    get pageIds() { return Object.keys(this.names) },
                    get allSelected() { return this.pageIds.length > 0 && this.selected.length === this.pageIds.length },
                    get someSelected() { return this.selected.length > 0 && ! this.allSelected },
                    toggleAll(on) { this.selected = on ? [...this.pageIds] : [] },
                }"
                @keydown.escape.window="confirming = false"
            >
            <div
                x-show="selected.length"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-y border-red-200 bg-red-50 px-6 py-3 dark:border-red-900/50 dark:bg-red-900/20"
                role="region"
                aria-label="Selected students"
            >
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">
                    <i class="fa-solid fa-square-check mr-1" aria-hidden="true"></i>
                    <span x-text="selected.length"></span>
                    <span x-text="selected.length === 1 ? 'student/pupil selected' : 'students/pupils selected'"></span>
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="selected = []" class="btn rounded-[8px] border border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Clear selection
                    </button>
                    <button type="button" @click="confirming = true" class="btn rounded-[8px] bg-red-600 font-semibold text-white hover:bg-red-700">
                        <i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> <span x-text="'Delete selected (' + selected.length + ')'">Delete selected</span>
                    </button>
                </div>
            </div>

            <form x-ref="bulkDelete" method="POST" action="{{ route('students.bulk-destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
                @foreach (request()->only('search', 'class', 'per_page') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="students[]" :value="id">
                </template>
            </form>

            {{-- Confirm, naming everyone about to be deleted. --}}
            <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="bulk-delete-title">
                <div @click.outside="confirming = false" x-show="confirming" x-transition class="w-full max-w-md rounded-[10px] bg-white p-6 shadow-xl dark:bg-gray-800">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 id="bulk-delete-title" class="text-base font-bold text-gray-900 dark:text-white">
                                Delete <span x-text="selected.length"></span> <span x-text="selected.length === 1 ? 'student/pupil' : 'students/pupils'"></span>?
                            </h3>
                            <small class="mt-1 block text-sm text-gray-600 dark:text-gray-300">This cannot be undone. Their records, results links and login details will be removed.</small>
                        </div>
                    </div>
                    <ul class="mt-4 max-h-48 space-y-1 overflow-y-auto rounded-[8px] bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900/50 dark:text-gray-200">
                        <template x-for="id in selected" :key="id">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-user text-xs text-gray-400" aria-hidden="true"></i><span x-text="names[id]"></span></li>
                        </template>
                    </ul>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="confirming = false" class="btn rounded-[8px] border border-gray-300 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel
                        </button>
                        <button type="button" @click="$refs.bulkDelete.submit()" class="btn rounded-[8px] bg-red-600 font-semibold text-white hover:bg-red-700">
                            <i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> <span x-text="'Delete ' + selected.length">Delete</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="w-10 py-3 pl-6 pr-0">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-gray-300 text-red-600 focus:ring-red-500"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    @change="toggleAll($event.target.checked)"
                                    :disabled="pageIds.length === 0"
                                    aria-label="Select every student on this page"
                                    data-tooltip="Select all on this page"
                                >
                            </th>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Admission No.</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Guardian</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($students as $student)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50" :class="selected.includes(@js($student->uuid)) && '!bg-red-50/70 dark:!bg-red-900/15'">
                                <td class="w-10 py-3 pl-6 pr-0">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 cursor-pointer rounded border-gray-300 text-red-600 focus:ring-red-500"
                                        value="{{ $student->uuid }}"
                                        x-model="selected"
                                        aria-label="Select {{ $student->fullName() }}"
                                    >
                                </td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('students.show', $student) }}" class="flex items-center gap-3">
                                        @if ($student->photoUrl())
                                            <img data-fallback="{{ $student->initials() }}" src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white">{{ $student->fullName() }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $student->class_name ?? 'N/A' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $student->guardian_name ?? 'N/A' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $student->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="icon-btn-group">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $student->uuid,
                                                'admission_number' => $student->admission_number,
                                                'first_name' => $student->first_name,
                                                'last_name' => $student->last_name,
                                                'gender' => $student->gender->value,
                                                'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                                                'class_name' => $student->class_name,
                                                'house' => $student->house,
                                                'guardian_name' => $student->guardian_name,
                                                'guardian_phone' => $student->guardian_phone,
                                                'guardian_email' => $student->guardian_email,
                                                'address' => $student->address,
                                                'phone' => $student->phone,
                                                'email' => $student->email,
                                                'admission_date' => $student->admission_date?->format('Y-m-d'),
                                                'notes' => $student->notes,
                                            ]); loginUsernameOverride = null; open = true"
                                            class="icon-btn icon-btn--edit"
                                            data-tooltip="Edit student"
                                            aria-label="Edit {{ $student->fullName() }}"
                                        >
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </button>
                                        <form method="POST" action="{{ route('students.toggle-active', $student) }}">
                                            @csrf @method('POST')
                                            @php($isActive = $student->is_active)
                                            <button type="submit" class="icon-btn {{ $isActive ? 'icon-btn--warn' : 'icon-btn--approve' }}" data-tooltip="{{ $isActive ? 'Deactivate' : 'Activate' }}" aria-label="{{ $isActive ? 'Deactivate' : 'Activate' }} {{ $student->fullName() }}">
                                                <i class="fa-solid {{ $isActive ? 'fa-toggle-off' : 'fa-toggle-on' }}" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Remove {{ $student->fullName() }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="icon-btn icon-btn--delete" data-tooltip="Delete student" aria-label="Delete {{ $student->fullName() }}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if (request('search') || request('class'))
                                        No students match your filters.
                                    @else
                                        No students yet. Click "Add Student" to enroll your first one, or "Bulk Upload" to add a whole class from a file.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4 dark:border-gray-700">
                {{-- More per page, so a larger batch can be ticked at once. --}}
                <form method="GET" action="{{ route('students.index') }}" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    @foreach (request()->only('search', 'class') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label for="per_page">Show</label>
                    <select id="per_page" name="per_page" class="w-20" onchange="this.form.submit()">
                        @foreach ([15, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($students->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span>per page</span>
                </form>

                @if ($students->hasPages())
                    <div class="min-w-0 flex-1">{{ $students->links() }}</div>
                @endif
            </div>
            </div>
        </div>

        {{-- Add/Edit Student modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Student' : 'Add Student'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('students.update', ['student' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('students.store') }}'"
                    enctype="multipart/form-data"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @if ($school->auto_generate_admission_numbers)
                            <div>
                                <input type="text" readonly tabindex="-1"
                                    x-bind:value="editing ? editing.admission_number : nextAdmissionNumberPreview(newClassName)"
                                    class="block w-full min-w-0">
                                <input type="hidden" name="admission_number" x-bind:value="editing ? editing.admission_number : nextAdmissionNumberPreview(newClassName)">
                                <small class="field-hint mt-1">Admission Number &mdash; generated automatically, <span x-show="!editing">shown here for review</span><span x-show="editing">locked after creation</span>.</small>
                            </div>
                        @else
                            <x-text-field name="admission_number" label="Admission Number" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.admission_number : ''" required />
                        @endif
                        <x-select-field
                            name="gender"
                            label="Gender"
                            required
                            model="editing ? editing.gender : 'male'"
                            :options="collect($genderOptions)->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all()"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="first_name" label="First Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.first_name : ''" required />
                        <x-text-field name="last_name" label="Last Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.last_name : ''" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-text-field name="date_of_birth" label="Date of Birth" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.date_of_birth : ''" />
                        <x-select-field
                            name="class_name"
                            label="Class"
                            model="editing ? editing.class_name : newClassName"
                            placeholder="No class assigned"
                            helper="Manage classes from the Academics section."
                            :options="['' => 'No class assigned'] + $academicLevels->flatMap->classes->pluck('name', 'name')->all()"
                        />
                        <x-text-field name="house" label="House" icon="M4 10.5L12 4l8 6.5V19a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1v-8.5z" x-model="editing ? editing.house : ''" placeholder="e.g. Blue House" />
                    </div>

                    {{-- Camera or upload, previewed either way, and nothing
                         attached until it is confirmed. Bound to whichever
                         pupil this form saves, there is no id in the field
                         for a photograph to follow to the wrong record. --}}
                    <x-photo-field
                        name="photo"
                        id="student_photo"
                        label="Passport Photograph"
                        helper="Optional. Take one with the camera or upload a JPG, PNG or WebP up to 10MB. Leave blank to keep the existing one when editing."
                    />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-text-field name="guardian_name" label="Guardian Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.guardian_name : ''" />
                        <x-text-field name="guardian_phone" label="Guardian Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.guardian_phone : ''" />
                        <x-text-field name="guardian_email" label="Guardian Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" x-model="editing ? editing.guardian_email : ''" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-text-field name="phone" label="Student Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.phone : ''" />
                        <x-text-field name="email" label="Student Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" x-model="editing ? editing.email : ''" />
                        <x-text-field name="admission_date" label="Admission Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.admission_date : ''" />
                    </div>

                    <x-textarea-field name="address" label="Address" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" rows="2" x-text="editing ? editing.address : ''" />
                    <x-textarea-field name="notes" label="Notes" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="2" x-text="editing ? editing.notes : ''" />

                    {{-- Only where there is a student portal to sign in to.
                         On Basic there is none, so login details would create
                         an account nobody could use. --}}
                    @if ($school->hasPortalAccounts())
                        <x-login-details-fields
                            username-label="Username (Admission Number)"
                            username-model="loginUsername"
                            username-hint="The Admission Number and password this student signs in to the student portal with."
                        />
                    @endif

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
