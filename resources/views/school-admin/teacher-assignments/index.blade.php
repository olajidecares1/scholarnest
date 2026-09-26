@php
    $typeBadge = fn (\App\Enums\TeacherAssignmentType $type) => match ($type) {
        \App\Enums\TeacherAssignmentType::ClassTeacher => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        \App\Enums\TeacherAssignmentType::SubjectTeacher => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };
@endphp

<x-dashboard-layout page-title="Teacher Assignments" page-subtitle="Assign teachers as Class Teachers or Subject Teachers.">
    <div class="space-y-6" x-data="{
        type: 'class_teacher',
        className: '',
        offerings: @js($offeringsByClass),
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

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Add Assignment</h2>
            <form method="POST" action="{{ route('teacher-assignments.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                @csrf
                <x-select-field
                    name="staff_uuid"
                    label="Teacher"
                    required
                    :options="collect($teachers)->mapWithKeys(fn ($t) => [$t->uuid => $t->fullName()])->all()"
                />
                <x-select-field
                    name="type"
                    label="Assignment Type"
                    required
                    model="type"
                    :options="collect($typeOptions)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()"
                />
                <x-select-field
                    name="class_name"
                    label="Class"
                    required
                    model="className"
                    :options="$classOptions->all()"
                />
                <div x-show="type === 'subject_teacher'">
                    <template x-if="offerings[className]">
                        <div>
                            <label class="field-label">Subject</label>
                            <select name="subject" class="mt-1 w-full">
                                <template x-for="option in offerings[className]" :key="option">
                                    <option :value="option" x-text="option"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <template x-if="!offerings[className]">
                        <div>
                            <label class="field-label">Subject</label>
                            <input list="subject-options" name="subject" placeholder="e.g. Mathematics" class="mt-1 w-full">
                            <datalist id="subject-options">
                                @foreach ($subjectOptions as $subject)
                                    <option value="{{ $subject }}">
                                @endforeach
                            </datalist>
                        </div>
                    </template>
                </div>
                <div class="lg:col-span-4">
                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Save Assignment</button>
                </div>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <x-select-field name="teacher" label="Teacher" placeholder="All Teachers" :selected="$selectedTeacher" :options="['' => 'All Teachers', ...collect($teachers)->mapWithKeys(fn ($t) => [$t->uuid => $t->fullName()])->all()]" />
                <x-select-field name="class" label="Class" placeholder="All Classes" :selected="$selectedClass" :options="['' => 'All Classes', ...$classOptions->all()]" />
                <x-text-field name="subject" label="Subject" :value="$selectedSubject" placeholder="Search subject..." />
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
                    @if ($selectedTeacher || $selectedClass || $selectedSubject)
                        <a href="{{ route('teacher-assignments.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="space-y-4">
            @forelse ($assignments as $staffAssignments)
                @php $staffMember = $staffAssignments->first()->staff; @endphp
                <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <div class="border-b border-gray-100 px-6 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $staffMember->fullName() }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-2 font-semibold">Type</th>
                                    <th class="px-6 py-2 font-semibold">Class</th>
                                    <th class="px-6 py-2 font-semibold">Subject</th>
                                    <th class="px-6 py-2 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($staffAssignments as $assignment)
                                    <tr>
                                        <td class="px-6 py-2.5">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $typeBadge($assignment->type) }}">{{ $assignment->type->label() }}</span>
                                        </td>
                                        <td class="px-6 py-2.5 text-gray-700 dark:text-gray-200">{{ $assignment->class_name }}</td>
                                        <td class="px-6 py-2.5 text-gray-700 dark:text-gray-200">{{ $assignment->subject ?? 'N/A' }}</td>
                                        <td class="px-6 py-2.5 text-right">
                                            <form method="POST" action="{{ route('teacher-assignments.destroy', $assignment) }}" onsubmit="return confirm('Remove this assignment for {{ $staffMember->fullName() }}?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No assignments yet</p>
                    <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Use the form above to assign a teacher as a Class Teacher or Subject Teacher.</small>
                </div>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
