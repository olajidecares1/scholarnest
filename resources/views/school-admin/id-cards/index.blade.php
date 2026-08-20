@php
    $studentClasses = $students->pluck('class_name')->filter()->unique()->sort()->values();
    $teachingDepartments = $teachingStaff->pluck('department')->filter()->unique()->sort()->values();
    $nonTeachingDepartments = $nonTeachingStaff->pluck('department')->filter()->unique()->sort()->values();
@endphp

<x-dashboard-layout page-title="ID Card Management" page-subtitle="Generate, preview, and print student and staff ID cards.">
    <div class="space-y-6" x-data="{ tab: 'student', selected: [], studentFilter: '', teachingFilter: '', nonTeachingFilter: '' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <button type="button" @click="tab = 'student'; selected = []" :class="tab === 'student' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold transition-colors duration-200">Students</button>
                <button type="button" @click="tab = 'teaching'; selected = []" :class="tab === 'teaching' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold transition-colors duration-200">Teaching Staff</button>
                <button type="button" @click="tab = 'non_teaching'; selected = []" :class="tab === 'non_teaching' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold transition-colors duration-200">Non-Teaching Staff</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('id-cards.issued.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Issued Cards</a>
                <a href="{{ route('id-cards.templates.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Templates</a>
            </div>
        </div>

        {{-- Students tab --}}
        <div x-show="tab === 'student'" style="display: none;">
            @if ($studentTemplates->isEmpty())
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                    No student card template yet. <a href="{{ route('id-cards.templates.index') }}" class="font-semibold underline">Create one</a> first.
                </div>
            @else
                <form method="POST" action="{{ route('id-cards.print') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="type" value="student">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Template</label>
                            <select name="template" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach ($studentTemplates as $t)
                                    <option value="{{ $t->uuid }}" @selected($t->is_default)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Class</label>
                            <select x-model="studentFilter" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option value="">All Classes</option>
                                @foreach ($studentClasses as $className)
                                    <option value="{{ $className }}">{{ $className }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" formaction="{{ route('id-cards.pdf') }}" formtarget="_self" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                                Download PDF
                            </button>
                            <button type="submit" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">
                                Print Selected (<span x-text="selected.length"></span>)
                            </button>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" @click="selected = $event.target.checked ? Array.from($event.target.closest('table').querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none').map(row => row.querySelector('input[type=checkbox][name=\'records[]\']').value) : []">
                                    </th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Admission No.</th>
                                    <th class="px-4 py-3">Class</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($students as $student)
                                    <tr x-show="studentFilter === '' || studentFilter === @js($student->class_name)">
                                        <td class="px-4 py-3"><input type="checkbox" name="records[]" value="{{ $student->uuid }}" x-model="selected"></td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $student->admission_number }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $student->class_name }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" @click="$store.idCardPreview.openPreview('{{ route('id-cards.preview', ['student', $student]) }}', '{{ route('id-cards.print') }}', '{{ route('id-cards.pdf') }}')" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Preview</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No students yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            @endif
        </div>

        {{-- Teaching Staff tab --}}
        <div x-show="tab === 'teaching'" style="display: none;">
            @if ($teachingStaffTemplates->isEmpty())
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                    No teaching staff card template yet. <a href="{{ route('id-cards.templates.index') }}" class="font-semibold underline">Create one</a> first.
                </div>
            @else
                <form method="POST" action="{{ route('id-cards.print') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="type" value="teaching_staff">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Template</label>
                            <select name="template" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach ($teachingStaffTemplates as $t)
                                    <option value="{{ $t->uuid }}" @selected($t->is_default)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Department</label>
                            <select x-model="teachingFilter" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option value="">All Departments</option>
                                @foreach ($teachingDepartments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" formaction="{{ route('id-cards.pdf') }}" formtarget="_self" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                                Download PDF
                            </button>
                            <button type="submit" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">
                                Print Selected (<span x-text="selected.length"></span>)
                            </button>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" @click="selected = $event.target.checked ? Array.from($event.target.closest('table').querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none').map(row => row.querySelector('input[type=checkbox][name=\'records[]\']').value) : []">
                                    </th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Staff No.</th>
                                    <th class="px-4 py-3">Department</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($teachingStaff as $member)
                                    <tr x-show="teachingFilter === '' || teachingFilter === @js($member->department)">
                                        <td class="px-4 py-3"><input type="checkbox" name="records[]" value="{{ $member->uuid }}" x-model="selected"></td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $member->fullName() }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->staff_number }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->department ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" @click="$store.idCardPreview.openPreview('{{ route('id-cards.preview', ['teaching_staff', $member]) }}', '{{ route('id-cards.print') }}', '{{ route('id-cards.pdf') }}')" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Preview</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No teaching staff yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            @endif
        </div>

        {{-- Non-Teaching Staff tab --}}
        <div x-show="tab === 'non_teaching'" style="display: none;">
            @if ($nonTeachingStaffTemplates->isEmpty())
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                    No non-teaching staff card template yet. <a href="{{ route('id-cards.templates.index') }}" class="font-semibold underline">Create one</a> first.
                </div>
            @else
                <form method="POST" action="{{ route('id-cards.print') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="type" value="non_teaching_staff">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Template</label>
                            <select name="template" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach ($nonTeachingStaffTemplates as $t)
                                    <option value="{{ $t->uuid }}" @selected($t->is_default)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Department</label>
                            <select x-model="nonTeachingFilter" class="rounded-[8px] border border-gray-300 bg-white py-2 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                <option value="">All Departments</option>
                                @foreach ($nonTeachingDepartments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" formaction="{{ route('id-cards.pdf') }}" formtarget="_self" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                                Download PDF
                            </button>
                            <button type="submit" :disabled="selected.length === 0" :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">
                                Print Selected (<span x-text="selected.length"></span>)
                            </button>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                                <tr>
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" @click="selected = $event.target.checked ? Array.from($event.target.closest('table').querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none').map(row => row.querySelector('input[type=checkbox][name=\'records[]\']').value) : []">
                                    </th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Staff No.</th>
                                    <th class="px-4 py-3">Department</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($nonTeachingStaff as $member)
                                    <tr x-show="nonTeachingFilter === '' || nonTeachingFilter === @js($member->department)">
                                        <td class="px-4 py-3"><input type="checkbox" name="records[]" value="{{ $member->uuid }}" x-model="selected"></td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $member->fullName() }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->staff_number }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->department ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" @click="$store.idCardPreview.openPreview('{{ route('id-cards.preview', ['non_teaching_staff', $member]) }}', '{{ route('id-cards.print') }}', '{{ route('id-cards.pdf') }}')" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Preview</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No non-teaching staff yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <x-id-card-preview-modal />
</x-dashboard-layout>
