<x-dashboard-layout page-title="Settings" page-subtitle="Manage your school's profile, branding, and preferences.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900">School Profile</h2>
            <p class="mt-0.5 text-xs text-gray-500">This information is used across your dashboard and public website.</p>

            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-16 w-16 rounded-[8px] border border-gray-200 object-cover">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400">No logo</span>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700">
                        <p class="mt-1 text-xs text-gray-500">School logo (optional). Leave blank to keep the existing one.</p>
                    </div>
                </div>

                <x-text-field name="name" label="School Name" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" value="{{ old('name', $school->name) }}" required />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select-field
                        name="timezone"
                        label="Timezone"
                        required
                        :selected="old('timezone', $school->timezone)"
                        :options="collect($timezones)->mapWithKeys(fn ($tz) => [$tz => $tz])->all()"
                    />
                    <x-text-field name="current_session" label="Current Academic Session" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" value="{{ old('current_session', $school->current_session) }}" placeholder="e.g. 2025/2026" helper="Used as the default session across the dashboard." />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</x-dashboard-layout>
