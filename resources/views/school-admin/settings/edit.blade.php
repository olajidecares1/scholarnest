<x-dashboard-layout page-title="Settings" page-subtitle="Manage your school's profile, branding, and preferences.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">School Profile</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">This information is used across your dashboard and public website.</p>

            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-4">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-16 w-16 rounded-[8px] border border-gray-200 object-cover dark:border-gray-700">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400 dark:border-gray-600 dark:text-gray-500">No logo</span>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">School logo (optional). Leave blank to keep the existing one.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    @if ($school->faviconUrl())
                        <img src="{{ $school->faviconUrl() }}" class="h-16 w-16 rounded-[8px] border border-gray-200 object-contain p-2 dark:border-gray-700">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-[8px] border border-dashed border-gray-300 text-xs font-semibold text-gray-400 dark:border-gray-600 dark:text-gray-500">No favicon</span>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp" class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Browser tab icon for your public website (optional, square PNG recommended). Your school's own icon is never replaced with EduNest's.</p>
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

                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="automatic_grading" value="1" {{ old('automatic_grading', $school->automatic_grading) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        Automatic Grading
                        <span class="block text-xs text-gray-500 dark:text-gray-400">When on, grades are assigned automatically from percentages using your configured grading rules. Turn off to manually override a grade per student on the score entry screen.</span>
                    </span>
                </label>

                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Identification Numbers</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Configure a school code and let EduNest generate unique Admission Numbers and Staff IDs automatically instead of typing them by hand.</p>

                    <div class="mt-4 space-y-4">
                        <x-text-field name="school_code" label="School Code" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" value="{{ old('school_code', $school->school_code) }}" placeholder="e.g. MIS" helper="Used as the prefix for every generated Admission Number and Staff ID." />

                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="auto_generate_admission_numbers" value="1" {{ old('auto_generate_admission_numbers', $school->auto_generate_admission_numbers) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>
                                Automatically Generate Admission Numbers
                                <span class="block text-xs text-gray-500 dark:text-gray-400">e.g. {{ $school->school_code ?: 'MIS' }}-{{ $school->current_session ?: '2025/2026' }}-PRY-{{ str_pad((string) $school->next_admission_sequence, 3, '0', STR_PAD_LEFT) }}. Once on, the field is generated for review on the student form and can't be hand-typed.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="auto_generate_staff_ids" value="1" {{ old('auto_generate_staff_ids', $school->auto_generate_staff_ids) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>
                                Automatically Generate Staff IDs
                                <span class="block text-xs text-gray-500 dark:text-gray-400">e.g. {{ $school->school_code ?: 'MIS' }}-STAFF-{{ str_pad((string) $school->next_staff_sequence, 3, '0', STR_PAD_LEFT) }}. Once on, the field is generated for review on the staff form and can't be hand-typed.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</x-dashboard-layout>
