<x-dashboard-layout :page-title="$member->fullName()" page-subtitle="Staff Profile">
    <div class="space-y-6">
        <a href="{{ route('staff.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Staff
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center gap-4">
                @if ($member->photoUrl())
                    <img src="{{ $member->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        {{ Str::of($member->first_name)->substr(0, 1)->upper() }}{{ Str::of($member->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $member->fullName() }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $member->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $member->staff_number }}
                        &middot; {{ $member->role->label() }}
                        @if ($member->department) &middot; {{ $member->department }} @endif
                        @if ($member->age()) &middot; {{ $member->age() }} years old @endif
                    </p>
                </div>
                <a href="{{ route('staff.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Edit in Staff List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Staff Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->gender->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Role</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->role->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Department</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->department ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Qualification</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->qualification ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Employment Date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->employment_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $member->address ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Emergency Contact</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->emergency_contact_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $member->emergency_contact_phone ?? '—' }}</dd></div>
                </dl>

                @if ($member->notes)
                    <h3 class="mt-6 text-sm font-bold text-gray-900 dark:text-white">Notes</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $member->notes }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Staff Portal Access</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $member->password ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                    {{ $member->password ? 'Access Enabled' : 'No Password Set' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Login: {{ $member->staff_number }} (or their email, if set)
                @if ($member->last_login_at)
                    &middot; Last logged in {{ $member->last_login_at->diffForHumans() }}
                @else
                    &middot; Has never logged in
                @endif
            </p>
            <form method="POST" action="{{ route('staff.update-password', $member) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div class="w-64">
                    <x-text-field name="password" label="Set Portal Password" type="password" icon="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4" helper="At least 6 characters." required />
                </div>
                <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">{{ $member->password ? 'Reset Password' : 'Enable Portal Access' }}</button>
            </form>
        </div>
    </div>
</x-dashboard-layout>
