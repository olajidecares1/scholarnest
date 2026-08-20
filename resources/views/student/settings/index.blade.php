<x-student-layout page-title="Settings" page-subtitle="Manage your profile and password">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Profile Photo & Contact Details</h2>
            <form method="POST" action="{{ route('student.settings.update-profile', $school) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($student->photoUrl())
                        <img src="{{ $student->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ Str::of($student->first_name)->substr(0, 1)->upper() }}</span>
                    @endif
                    <p class="text-xs text-gray-400 dark:text-gray-500">Your photo can only be changed by the school office.</p>
                </div>

                <x-text-field name="phone" label="Phone" value="{{ $student->phone }}" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" />
                <x-text-field name="email" type="email" label="Email" value="{{ $student->email }}" icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7" />
                <x-textarea-field name="address" label="Address" rows="2" value="{{ $student->address }}" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" />

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Change Password</h2>
            <form method="POST" action="{{ route('student.settings.update-password', $school) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-password-field name="current_password" label="Current Password" required />
                <x-password-field name="password" label="New Password" required />
                <x-password-field name="password_confirmation" label="Confirm New Password" required />

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700">Update Password</button>
                </div>
            </form>
        </div>

        <x-profile-change-request-card
            :action="route('student.profile-change-requests.store', $school)"
            :fields="$protectedFields"
            :requests="$changeRequests"
        />
    </div>
</x-student-layout>
