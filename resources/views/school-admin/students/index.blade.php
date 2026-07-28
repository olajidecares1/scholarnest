@php
    $genderOptions = \App\Enums\Gender::cases();
@endphp

<x-dashboard-layout page-title="Students" page-subtitle="Manage your school's student records.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm lg:rounded-[10px]">
                <p class="text-sm font-medium text-purple-600">Total Students</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($totalCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm lg:rounded-[10px]">
                <p class="text-sm font-medium text-green-600">Active Students</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($activeCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6">
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search by name or admission number..."
                        class="w-64 rounded-[5px] border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10 lg:rounded-[8px]"
                    >
                    <select name="class" class="rounded-[5px] border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10 lg:rounded-[8px]" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        @foreach ($classes as $className)
                            <option value="{{ $className }}" @selected(request('class') === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 lg:rounded-[8px]">Filter</button>
                    @if (request('search') || request('class'))
                        <a href="{{ route('students.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="flex items-center gap-2 rounded-[5px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md lg:rounded-[10px]"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Student
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Admission No.</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Guardian</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($students as $student)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('students.show', $student) }}" class="flex items-center gap-3">
                                        @if ($student->photoUrl())
                                            <img src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
                                                {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-gray-900 hover:text-blue-600">{{ $student->fullName() }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $student->admission_number }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $student->class_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $student->guardian_name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
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
                                                'guardian_name' => $student->guardian_name,
                                                'guardian_phone' => $student->guardian_phone,
                                                'guardian_email' => $student->guardian_email,
                                                'address' => $student->address,
                                                'phone' => $student->phone,
                                                'email' => $student->email,
                                                'admission_date' => $student->admission_date?->format('Y-m-d'),
                                                'notes' => $student->notes,
                                            ]); open = true"
                                            class="rounded-[5px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 lg:rounded-[8px]"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('students.toggle-active', $student) }}">
                                            @csrf @method('POST')
                                            <button type="submit" class="rounded-[5px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 lg:rounded-[8px]">
                                                {{ $student->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Remove {{ $student->fullName() }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[5px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 lg:rounded-[8px]">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
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
                <div class="border-t border-gray-100 p-4">
                    {{ $students->links() }}
                </div>
            @endif
        </div>

        {{-- Add/Edit Student modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[5px] bg-white p-6 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900" x-text="editing ? 'Edit Student' : 'Add Student'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('students.update', ['student' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('students.store') }}'"
                    enctype="multipart/form-data"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="admission_number" label="Admission Number" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.admission_number : ''" required />
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Gender</label>
                            <select name="gender" required x-model="editing ? editing.gender : 'male'" class="w-full rounded-[8px] border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                                @foreach ($genderOptions as $gender)
                                    <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="first_name" label="First Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.first_name : ''" required />
                        <x-text-field name="last_name" label="Last Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.last_name : ''" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="date_of_birth" label="Date of Birth" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.date_of_birth : ''" />
                        <x-text-field name="class_name" label="Class" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" helper="e.g. JSS 1, SS 2" x-model="editing ? editing.class_name : ''" />
                    </div>

                    <div>
                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[5px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[5px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 lg:rounded-[10px]">
                        <p class="mt-1 text-xs text-gray-500">Photo (optional). Leave blank to keep the existing one when editing.</p>
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

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 lg:rounded-[10px]">Cancel</button>
                        <button type="submit" class="rounded-[5px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 lg:rounded-[10px]">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
