<x-staff-layout page-title="My Profile" page-subtitle="Your staff record">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-4">
                @if ($staff->photoUrl())
                    <img src="{{ $staff->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                        {{ Str::of($staff->first_name)->substr(0, 1)->upper() }}{{ Str::of($staff->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $staff->fullName() }}</h2>
                    <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $staff->staff_number }} &middot; {{ $staff->role->label() }}
                        @if ($staff->department) &middot; {{ $staff->department }} @endif
                    </small>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Personal Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->gender?->label() ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->date_of_birth?->format('M j, Y') ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->phone ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->email ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $staff->address ?? 'N/A' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Employment Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Qualification</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->qualification ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Employment Date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->employment_date?->format('M j, Y') ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Emergency Contact</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->emergency_contact_name ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Emergency Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $staff->emergency_contact_phone ?? 'N/A' }}</dd></div>
                </dl>
                <small class="block mt-6 text-xs text-gray-400 dark:text-gray-500">To update these details, please contact your school office.</small>
            </div>
        </div>
    </div>
</x-staff-layout>
