@php
    $genderOptions = \App\Enums\Gender::cases();
    $roleOptions = \App\Enums\StaffRole::cases();
@endphp

<x-dashboard-layout page-title="Staff" page-subtitle="Manage your school's staff records.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-purple-600">Total Staff</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-green-600">Active Staff</p>
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
                        placeholder="Search by name or staff number..."
                        class="h-11 w-64 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                    <div
                        class="w-48"
                        x-data="{ roleFilter: @js(request('role', '')) }"
                        x-init="$watch('roleFilter', () => $el.closest('form').submit())"
                    >
                        <x-select-field
                            name="role"
                            model="roleFilter"
                            placeholder="All Roles"
                            :options="['' => 'All Roles'] + collect($roleOptions)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()"
                        />
                    </div>
                    <button type="submit" class="h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Search</button>
                    @if (request('search') || request('role'))
                        <a href="{{ route('staff.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Staff
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Staff</th>
                            <th class="px-6 py-3 font-semibold">Staff No.</th>
                            <th class="px-6 py-3 font-semibold">Role</th>
                            <th class="px-6 py-3 font-semibold">Department</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($staff as $member)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('staff.show', $member) }}" class="flex items-center gap-3">
                                        @if ($member->photoUrl())
                                            <img src="{{ $member->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                {{ Str::of($member->first_name)->substr(0, 1)->upper() }}{{ Str::of($member->last_name)->substr(0, 1)->upper() }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-gray-900 hover:text-blue-600 dark:text-white">{{ $member->fullName() }}</span>
                                    </a>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $member->staff_number }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $member->role->label() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $member->department ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $member->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $member->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $member->uuid,
                                                'staff_number' => $member->staff_number,
                                                'first_name' => $member->first_name,
                                                'last_name' => $member->last_name,
                                                'gender' => $member->gender->value,
                                                'date_of_birth' => $member->date_of_birth?->format('Y-m-d'),
                                                'role' => $member->role->value,
                                                'department' => $member->department,
                                                'qualification' => $member->qualification,
                                                'employment_date' => $member->employment_date?->format('Y-m-d'),
                                                'emergency_contact_name' => $member->emergency_contact_name,
                                                'emergency_contact_phone' => $member->emergency_contact_phone,
                                                'address' => $member->address,
                                                'phone' => $member->phone,
                                                'email' => $member->email,
                                                'notes' => $member->notes,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('staff.toggle-active', $member) }}">
                                            @csrf @method('POST')
                                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.destroy', $member) }}" onsubmit="return confirm('Remove {{ $member->fullName() }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if (request('search') || request('role'))
                                        No staff match your filters.
                                    @else
                                        No staff yet. Click "Add Staff" to add your first team member.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($staff->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $staff->links() }}
                </div>
            @endif
        </div>

        {{-- Add/Edit Staff modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Staff Member' : 'Add Staff Member'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('staff.update', ['member' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('staff.store') }}'"
                    enctype="multipart/form-data"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="staff_number" label="Staff Number" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.staff_number : ''" required />
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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field
                            name="role"
                            label="Role"
                            required
                            model="editing ? editing.role : 'teacher'"
                            :options="collect($roleOptions)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()"
                        />
                        <x-text-field name="department" label="Department / Subject" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.department : ''" helper="E.g. Mathematics, Administration." />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="date_of_birth" label="Date of Birth" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.date_of_birth : ''" />
                        <x-text-field name="employment_date" label="Employment Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.employment_date : ''" />
                    </div>

                    <x-text-field name="qualification" label="Qualification" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.qualification : ''" helper="E.g. B.Sc, B.Ed, M.Sc, NCE." />

                    <div>
                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Photo (optional). Leave blank to keep the existing one when editing.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-text-field name="phone" label="Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.phone : ''" />
                        <x-text-field name="email" label="Email" type="email" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" x-model="editing ? editing.email : ''" />
                        <x-text-field name="emergency_contact_phone" label="Emergency Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.emergency_contact_phone : ''" />
                    </div>

                    <x-text-field name="emergency_contact_name" label="Emergency Contact Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.emergency_contact_name : ''" />

                    <x-textarea-field name="address" label="Address" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" rows="2" x-text="editing ? editing.address : ''" />
                    <x-textarea-field name="notes" label="Notes" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="2" x-text="editing ? editing.notes : ''" />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
