<x-dashboard-layout :page-title="$member->fullName()" page-subtitle="Staff Profile">
    <div class="space-y-6">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Staff
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <div class="flex flex-wrap items-center gap-4">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700">
                        {{ Str::of($member->first_name)->substr(0, 1)->upper() }}{{ Str::of($member->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900">{{ $member->fullName() }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $member->staff_number }}
                        &middot; {{ $member->role->label() }}
                        @if ($member->department) &middot; {{ $member->department }} @endif
                        @if ($member->age()) &middot; {{ $member->age() }} years old @endif
                    </p>
                </div>
                <a href="{{ route('staff.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50">
                    Edit in Staff List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900">Staff Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Gender</dt><dd class="font-medium text-gray-900">{{ $member->gender->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Date of Birth</dt><dd class="font-medium text-gray-900">{{ $member->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Role</dt><dd class="font-medium text-gray-900">{{ $member->role->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Department</dt><dd class="font-medium text-gray-900">{{ $member->department ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Qualification</dt><dd class="font-medium text-gray-900">{{ $member->qualification ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Employment Date</dt><dd class="font-medium text-gray-900">{{ $member->employment_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Phone</dt><dd class="font-medium text-gray-900">{{ $member->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Email</dt><dd class="font-medium text-gray-900">{{ $member->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Address</dt><dd class="text-right font-medium text-gray-900">{{ $member->address ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900">Emergency Contact</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Name</dt><dd class="font-medium text-gray-900">{{ $member->emergency_contact_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">Phone</dt><dd class="font-medium text-gray-900">{{ $member->emergency_contact_phone ?? '—' }}</dd></div>
                </dl>

                @if ($member->notes)
                    <h3 class="mt-6 text-sm font-bold text-gray-900">Notes</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $member->notes }}</p>
                @endif
            </div>
        </div>
    </div>
</x-dashboard-layout>
