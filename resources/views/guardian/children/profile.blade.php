<x-guardian-layout page-title="Child Profile" :page-subtitle="$student->fullName().'\'s record'" :active-child="$activeChild">
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-4">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                        {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $student->admission_number }}
                        @if ($student->class_name) &middot; {{ $student->class_name }} @endif
                        @if ($student->age()) &middot; {{ $student->age() }} years old @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Details</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->gender->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->date_of_birth?->format('M j, Y') ?? 'N/A' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Admission Date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->admission_date?->format('M j, Y') ?? 'N/A' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->phone ?? 'N/A' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->email ?? 'N/A' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $student->address ?? 'N/A' }}</dd></div>
            </dl>
            <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">To update these details, please contact the school office.</p>
        </div>

        <x-check-result-card
            :results-url="route('guardian.children.results', [$school, $student])"
            :name="$student->first_name"
            :locked="$resultLocks['locked']"
            :unlocked="$resultLocks['unlocked']"
        />

        <x-profile-change-request-card
            :action="route('guardian.children.profile-change-requests.store', [$school, $student])"
            :fields="$protectedFields"
            :requests="$changeRequests"
        />
    </div>
</x-guardian-layout>
