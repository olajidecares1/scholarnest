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
        nextAdmissionSequence: @js($school->next_admission_sequence),
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
                    <x-stat-tooltip text="Students currently marked active — inactive students are excluded from attendance, exams, and class lists." />
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
                    <button type="submit" class="h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Search</button>
                    @if (request('search') || request('class'))
                        <a href="{{ route('students.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="editing = null; newClassName = ''; open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Student
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
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
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('students.show', $student) }}" class="flex items-center gap-3">
                                        @if ($student->photoUrl())
                                            <img src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white">{{ $student->fullName() }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $student->class_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $student->guardian_name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $student->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
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
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('students.toggle-active', $student) }}">
                                            @csrf @method('POST')
                                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                {{ $student->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Remove {{ $student->fullName() }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if (request('search') || request('class'))
                                        No students match your filters.
                                    @else
                                        No students yet. Click "Add Student" to enroll your first one.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($students->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $students->links() }}
                </div>
            @endif
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
                                <p class="field-hint mt-1">Admission Number &mdash; generated automatically, <span x-show="!editing">shown here for review</span><span x-show="editing">locked after creation</span>.</p>
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

                    <div>
                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="w-full">
                        <p class="field-hint mt-1">Photo (optional). Leave blank to keep the existing one when editing.</p>
                    </div>

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
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
