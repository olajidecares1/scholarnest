<x-staff-layout page-title="My Class" page-subtitle="Add new students/pupils to your class and correct their names.">
    {{-- A Class Teacher's own class list.

         Add and rename only. There is no delete button because there is no
         delete: removing a student's record removes their results with it,
         and that is for the School Admin alone (StudentPolicy). --}}
    <div class="space-y-6" x-data="{ adding: @js($errors->any() && old('class_name') !== null), editing: null }">
        @if (session('status'))
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

                <button type="button" x-on:click="adding = ! adding" class="btn rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    <i class="fa-solid fa-user-plus btn-icon" aria-hidden="true"></i> Add Student/Pupil
                </button>
            </div>

            {{-- Add a pupil to one of this teacher's classes. --}}
            <form
                method="POST"
                action="{{ route('staff.students.store', $school) }}"
                x-show="adding"
                x-cloak
                class="rounded-[10px] border border-primary-200 bg-white p-5 shadow-sm dark:border-primary-900 dark:bg-gray-800"
            >
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Add a student/pupil</h2>
                <small class="field-hint mt-0.5">
                    {{ $autoAdmissionNumbers ? 'An admission number is given automatically.' : 'Enter the admission number the school has given this student/pupil.' }}
                    Your School Admin can add the rest of their details.
                </small>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="add_class" class="field-label mb-1">Class</label>
                        <select id="add_class" name="class_name" required class="w-full">
                            @foreach ($classes as $class)
                                <option value="{{ $class }}" @selected(old('class_name', $selectedClass) === $class)>{{ $class }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="add_first_name" class="field-label mb-1">First Name</label>
                        <input id="add_first_name" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100" class="w-full">
                    </div>
                    <div>
                        <label for="add_last_name" class="field-label mb-1">Surname</label>
                        <input id="add_last_name" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100" class="w-full">
                    </div>
                    <div>
                        <label for="add_gender" class="field-label mb-1">Gender</label>
                        <select id="add_gender" name="gender" required class="w-full">
                            <option value="">Choose…</option>
                            @foreach ($genders as $gender)
                                <option value="{{ $gender->value }}" @selected(old('gender') === $gender->value)>{{ $gender->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="add_dob" class="field-label mb-1">Date of Birth <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="add_dob" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subDay()->toDateString() }}" class="w-full">
                    </div>
                    @unless ($autoAdmissionNumbers)
                        <div>
                            <label for="add_admission" class="field-label mb-1">Admission Number</label>
                            <input id="add_admission" type="text" name="admission_number" value="{{ old('admission_number') }}" required maxlength="50" class="w-full font-mono">
                        </div>
                    @endunless
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" x-on:click="adding = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel
                    </button>
                    <button type="submit" class="btn rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        <i class="fa-solid fa-check btn-icon" aria-hidden="true"></i> Add to Class
                    </button>
                </div>
            </form>

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
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="w-12 px-5 py-2.5 font-semibold">S/N</th>
                                    <th class="px-3 py-2.5 font-semibold">Name</th>
                                    <th class="px-3 py-2.5 font-semibold">Admission No.</th>
                                    <th class="px-3 py-2.5 font-semibold">Gender</th>
                                    <th class="px-5 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($students as $student)
                                    <tr>
                                        <td class="px-5 py-2.5 tabular-nums text-gray-500">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2.5">
                                            <div x-show="editing !== @js($student->uuid)">
                                                <span class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                                @unless ($student->is_active)
                                                    <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-300">Inactive</span>
                                                @endunless
                                            </div>

                                            {{-- Names only: first name and surname. --}}
                                            <form
                                                method="POST"
                                                action="{{ route('staff.students.update-name', [$school, $student]) }}"
                                                x-show="editing === @js($student->uuid)"
                                                x-cloak
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="first_name" value="{{ $student->first_name }}" required maxlength="100" class="w-36 py-1.5 text-sm" aria-label="First name of {{ $student->fullName() }}">
                                                <input type="text" name="last_name" value="{{ $student->last_name }}" required maxlength="100" class="w-36 py-1.5 text-sm" aria-label="Surname of {{ $student->fullName() }}">
                                                <button type="submit" class="btn rounded-[6px] bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">
                                                    <i class="fa-solid fa-check btn-icon" aria-hidden="true"></i> Save
                                                </button>
                                                <button type="button" x-on:click="editing = null" class="btn rounded-[6px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300">Cancel</button>
                                            </form>
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $student->admission_number }}</td>
                                        <td class="px-3 py-2.5 text-gray-600 dark:text-gray-300">{{ $student->gender?->label() ?? 'N/A' }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            <button
                                                type="button"
                                                x-show="editing !== @js($student->uuid)"
                                                x-on:click="editing = @js($student->uuid)"
                                                class="icon-btn icon-btn--neutral"
                                                data-tooltip="Edit name"
                                                aria-label="Edit the name of {{ $student->fullName() }}"
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
                To remove a student/pupil or change any other detail, ask your School Admin.
            </small>
        @endif
    </div>
</x-staff-layout>
