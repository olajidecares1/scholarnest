<x-dashboard-layout :page-title="$student->fullName()" page-subtitle="Student Profile">
    <div class="space-y-6">
        <a href="{{ route('students.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Students
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center gap-4">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $student->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $student->admission_number }}
                        @if ($student->class_name) &middot; {{ $student->class_name }} @endif
                        @if ($student->age()) &middot; {{ $student->age() }} years old @endif
                    </p>
                </div>
                <a href="{{ route('students.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Edit in Students List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Student Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->gender->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Class</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->class_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Admission Date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->admission_date?->format('M j, Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $student->address ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Guardian Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_email ?? '—' }}</dd></div>
                </dl>

                @if ($student->notes)
                    <h3 class="mt-6 text-sm font-bold text-gray-900 dark:text-white">Notes</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $student->notes }}</p>
                @endif
            </div>
        </div>
    </div>
</x-dashboard-layout>
