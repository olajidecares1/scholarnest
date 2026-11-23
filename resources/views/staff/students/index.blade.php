@php
    // Typed back in after a refused add or edit, so nothing is lost.
    $fields = ['class_name', 'first_name', 'last_name', 'gender', 'date_of_birth', 'house',
        'guardian_name', 'guardian_phone', 'guardian_email', 'address', 'phone', 'email', 'admission_date', 'notes'];
    $oldInput = collect($fields)->mapWithKeys(fn ($field) => [$field => old($field)])->filter(fn ($value) => $value !== null);
    $blank = collect($fields)->mapWithKeys(fn ($field) => [$field => ''])->merge(['class_name' => $selectedClass])->all();
@endphp

<x-staff-layout page-title="My Class" page-subtitle="Add students/pupils to your class and keep their details up to date.">
    {{-- A Class Teacher's own class list: add pupils, edit their details.

         No delete button, because there is no delete: removing a student's
         record removes their results with it, and that is for the School
         Admin alone. The admission number once set, login details and
         active status also stay with the School Admin. All enforced on the
         server by StudentPolicy and ClassStudentController. --}}
    <div
        class="space-y-6"
        x-data="{
            mode: null,
            form: {},
            blank: @js($blank),
            students: @js($editable),
            storeUrl: @js(route('staff.students.store', $school)),
            previews: @js($admissionPreviews ?? []),

            openAdd() { this.form = { ...this.blank }; this.mode = 'add' },
            openEdit(id) { this.form = { ...this.students[id], _student: id }; this.mode = 'edit' },
            close() { this.mode = null },

            init() {
                const mode = @js(old('_mode'));
                const typed = @js($oldInput);

                if (mode === 'add') {
                    this.form = { ...this.blank, ...typed };
                    this.mode = 'add';
                } else if (mode === 'edit' && this.students[@js(old('_student'))]) {
                    this.form = { ...this.students[@js(old('_student'))], ...typed, _student: @js(old('_student')) };
                    this.mode = 'edit';
                }
            },
        }"
        x-on:keydown.escape.window="close()"
    >
        @if (session('added_student'))
            {{-- A pupil was just added: the admission number they were given,
                 large enough to read off and write down. --}}
            @php $added = session('added_student'); @endphp
            <div class="rounded-[10px] border border-green-200 bg-green-50 p-4 dark:border-green-900/50 dark:bg-green-900/20" role="status">
                <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                    <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>
                    {{ $added['name'] }} was added to {{ $added['class'] }}.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <div>
                        <small class="block text-[11px] font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">Admission Number</small>
                        <span class="font-mono text-xl font-bold tracking-wide text-gray-900 dark:text-white">{{ $added['admission_number'] }}</span>
                    </div>
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard?.writeText(@js($added['admission_number'])).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="btn rounded-[8px] border border-green-300 bg-white px-3 py-1.5 text-xs font-semibold text-green-800 hover:bg-green-100 dark:border-green-800 dark:bg-gray-800 dark:text-green-300"
                    >
                        <i class="fa-solid btn-icon" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                </div>
            </div>
        @elseif (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <x-duplicate-student-notice :notices="session('duplicate_students', [])" />

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (empty($classes))
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
                <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Ask your School Admin to make you the Class Teacher of a class to add and manage its students/pupils.</small>
            </div>
        @else
            <div class="flex flex-wrap items-end justify-between gap-3">
                {{-- A class picker only for a teacher with more than one class. --}}
                @if (count($classes) > 1)
                    <form method="GET" action="{{ route('staff.students.index', $school) }}">
                        <label for="class" class="field-label mb-1">Class</label>
                        <select id="class" name="class" onchange="this.form.submit()" class="w-56">
                            @foreach ($classes as $class)
                                <option value="{{ $class }}" @selected($class === $selectedClass)>{{ $class }}</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <div>
                        <small class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Class</small>
                        <span class="text-base font-bold text-gray-900 dark:text-white">{{ $selectedClass }}</span>
                    </div>
                @endif

                <button type="button" x-on:click="openAdd()" @disabled(! $canNumber) class="btn rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <i class="fa-solid fa-user-plus btn-icon" aria-hidden="true"></i> Add Student/Pupil
                </button>
            </div>

            @unless ($canNumber)
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300">
                    New students/pupils cannot be added yet: admission numbers are generated from your school code, and your
                    school does not have one. Ask your School Admin to set it under Settings.
                </div>
            @endunless

            {{-- The class list. --}}
            <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $selectedClass }}</h2>
                    <small class="text-xs text-gray-500">{{ $students->count() }} student{{ $students->count() === 1 ? '' : 's' }}/pupil{{ $students->count() === 1 ? '' : 's' }}</small>
                </div>

                @if ($students->isEmpty())
                    <small class="block p-8 text-center text-sm text-gray-500 dark:text-gray-400">No students/pupils in {{ $selectedClass }} yet.</small>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    {{-- Kept on one line: "S/N" used to break into S, / and N on a phone. --}}
                                    <th class="w-14 whitespace-nowrap px-4 py-2.5 font-semibold">S/N</th>
                                    <th class="px-3 py-2.5 font-semibold">Name</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 font-semibold">Admission No.</th>
                                    <th class="px-3 py-2.5 font-semibold">Gender</th>
                                    <th class="px-3 py-2.5 font-semibold">Parent/Guardian</th>
                                    <th class="px-5 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($students as $student)
                                    <tr @class(['bg-green-50 dark:bg-green-900/20' => session('added_student.uuid') === $student->uuid])>
                                        <td class="w-14 whitespace-nowrap px-4 py-2.5 align-middle tabular-nums text-gray-500">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                            @unless ($student->is_active)
                                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                            @endunless
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                        <td class="px-3 py-2.5 text-gray-600 dark:text-gray-300">{{ $student->gender?->label() ?? 'N/A' }}</td>
                                        <td class="px-3 py-2.5 text-gray-600 dark:text-gray-300">
                                            {{ $student->guardian_name ?: '—' }}
                                            @if ($student->guardian_phone)
                                                <small class="block text-xs text-gray-500">{{ $student->guardian_phone }}</small>
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5 text-right">
                                            <button
                                                type="button"
                                                x-on:click="openEdit(@js($student->uuid))"
                                                class="icon-btn icon-btn--neutral"
                                                data-tooltip="Edit details"
                                                aria-label="Edit the details of {{ $student->fullName() }}"
                                            >
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <small class="block text-xs text-gray-500 dark:text-gray-400">
                <i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>
                To remove a student/pupil, change an admission number, set up login details, or deactivate a record, ask your School Admin.
            </small>

            {{-- One form for adding and editing: the same details either way. --}}
            <div x-show="mode !== null" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-900/60" x-on:click="close()"></div>

                <form
                    method="POST"
                    :action="mode === 'add' ? storeUrl : form.action"
                    enctype="multipart/form-data"
                    class="relative z-10 my-8 w-full max-w-3xl rounded-[12px] bg-white shadow-xl dark:bg-gray-800"
                >
                    @csrf
                    <input type="hidden" name="_mode" :value="mode">
                    <template x-if="mode === 'edit'">
                        <span>
                            <input type="hidden" name="_method" value="PUT">
                            <input type="hidden" name="_student" :value="form._student">
                        </span>
                    </template>

                    <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white" x-text="mode === 'add' ? 'Add a Student/Pupil' : 'Edit ' + form.name"></h2>
                            <small class="field-hint mt-0.5" x-show="mode === 'edit'">Admission number <span class="font-mono font-semibold" x-text="form.admission_number"></span></small>
                            <small class="field-hint mt-0.5" x-show="mode === 'add'">
                                <i class="fa-solid fa-wand-magic-sparkles mr-0.5" aria-hidden="true"></i>
                                The admission number is generated automatically when you add the student/pupil.
                            </small>
                        </div>
                        <button type="button" x-on:click="close()" class="icon-btn icon-btn--neutral shrink-0" data-tooltip="Close" aria-label="Close">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-5 overflow-y-auto px-5 py-4">
                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-wide text-gray-500">Student/Pupil</legend>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {{-- The number this pupil will get, from the School Admin's
                                     generator, for the class chosen below. Read only. --}}
                                <template x-if="mode === 'add'">
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <label for="f_admission_preview" class="field-label mb-1">Admission Number</label>
                                        <input
                                            id="f_admission_preview"
                                            type="text"
                                            :value="previews[form.class_name] ?? ''"
                                            readonly
                                            tabindex="-1"
                                            aria-describedby="f_admission_preview_hint"
                                            class="w-full cursor-default bg-gray-50 font-mono font-semibold text-gray-900 dark:bg-gray-900/40 dark:text-white"
                                        >
                                        <small id="f_admission_preview_hint" class="field-hint mt-1">
                                            Generated automatically for the class you choose. If someone else adds a pupil first, the next number is used; the number given is shown after you save.
                                        </small>
                                    </div>
                                </template>
                                <template x-if="mode === 'edit'">
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <label for="f_admission_existing" class="field-label mb-1">Admission Number</label>
                                        <input id="f_admission_existing" type="text" :value="form.admission_number" readonly tabindex="-1" class="w-full cursor-default bg-gray-50 font-mono font-semibold text-gray-900 dark:bg-gray-900/40 dark:text-white">
                                        <small class="field-hint mt-1">Only the School Admin can change an admission number.</small>
                                    </div>
                                </template>
                                <div>
                                    <label for="f_first_name" class="field-label mb-1">First Name</label>
                                    <input id="f_first_name" type="text" name="first_name" x-model="form.first_name" required maxlength="100" class="w-full">
                                </div>
                                <div>
                                    <label for="f_last_name" class="field-label mb-1">Surname</label>
                                    <input id="f_last_name" type="text" name="last_name" x-model="form.last_name" required maxlength="100" class="w-full">
                                </div>
                                <div>
                                    <label for="f_gender" class="field-label mb-1">Gender</label>
                                    <select id="f_gender" name="gender" x-model="form.gender" required class="w-full">
                                        <option value="">Choose…</option>
                                        @foreach ($genders as $gender)
                                            <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="f_class" class="field-label mb-1">Class</label>
                                    <select id="f_class" name="class_name" x-model="form.class_name" required class="w-full">
                                        @foreach ($classes as $class)
                                            <option value="{{ $class }}">{{ $class }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="f_dob" class="field-label mb-1">Date of Birth</label>
                                    <input id="f_dob" type="date" name="date_of_birth" x-model="form.date_of_birth" max="{{ now()->subDay()->toDateString() }}" class="w-full">
                                </div>
                                <div>
                                    <label for="f_admission_date" class="field-label mb-1">Admission Date</label>
                                    <input id="f_admission_date" type="date" name="admission_date" x-model="form.admission_date" class="w-full">
                                </div>
                                <div>
                                    <label for="f_house" class="field-label mb-1">House</label>
                                    <input id="f_house" type="text" name="house" x-model="form.house" maxlength="100" placeholder="e.g. Blue House" class="w-full">
                                </div>
                                <div>
                                    <label for="f_photo" class="field-label mb-1">Photo</label>
                                    <input id="f_photo" type="file" name="photo" accept="image/*" class="w-full text-sm">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-wide text-gray-500">Parent/Guardian</legend>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="f_guardian_name" class="field-label mb-1">Name</label>
                                    <input id="f_guardian_name" type="text" name="guardian_name" x-model="form.guardian_name" maxlength="150" class="w-full">
                                </div>
                                <div>
                                    <label for="f_guardian_phone" class="field-label mb-1">Phone</label>
                                    <input id="f_guardian_phone" type="tel" name="guardian_phone" x-model="form.guardian_phone" maxlength="30" class="w-full">
                                </div>
                                <div>
                                    <label for="f_guardian_email" class="field-label mb-1">Email</label>
                                    <input id="f_guardian_email" type="email" name="guardian_email" x-model="form.guardian_email" maxlength="255" class="w-full">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-wide text-gray-500">Contact &amp; Notes</legend>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="f_phone" class="field-label mb-1">Student/Pupil Phone</label>
                                    <input id="f_phone" type="tel" name="phone" x-model="form.phone" maxlength="30" class="w-full">
                                </div>
                                <div>
                                    <label for="f_email" class="field-label mb-1">Student/Pupil Email</label>
                                    <input id="f_email" type="email" name="email" x-model="form.email" maxlength="255" class="w-full">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="f_address" class="field-label mb-1">Address</label>
                                    <textarea id="f_address" name="address" x-model="form.address" rows="2" maxlength="2000" class="w-full"></textarea>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="f_notes" class="field-label mb-1">Notes</label>
                                    <textarea id="f_notes" name="notes" x-model="form.notes" rows="2" maxlength="2000" class="w-full"></textarea>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                        <button type="button" x-on:click="close()" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel
                        </button>
                        <button type="submit" class="btn rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            <i class="fa-solid fa-check btn-icon" aria-hidden="true"></i>
                            <span x-text="mode === 'add' ? 'Add to Class' : 'Save Changes'">Save</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-staff-layout>
