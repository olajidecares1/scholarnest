<x-super-admin-layout page-title="Add School" page-subtitle="Manually onboard a new school and its admin account.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.schools.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="school_name" value="School Name" />
                    <x-text-input
                        id="school_name"
                        name="school_name"
                        type="text"
                        class="mt-1"
                        :value="old('school_name')"
                        required
                        autofocus
                        autocomplete="organization"
                        placeholder="e.g. Bright Future Academy"
                    />
                    <x-input-error :messages="$errors->get('school_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="admin_name" value="Admin Full Name" />
                    <x-text-input
                        id="admin_name"
                        name="admin_name"
                        type="text"
                        class="mt-1"
                        :value="old('admin_name')"
                        required
                        autocomplete="name"
                        placeholder="e.g. Jane Doe"
                    />
                    <x-input-error :messages="$errors->get('admin_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="admin_email" value="Admin Email" />
                    <x-text-input
                        id="admin_email"
                        name="admin_email"
                        type="email"
                        class="mt-1"
                        :value="old('admin_email')"
                        required
                        autocomplete="email"
                        placeholder="admin@school.com"
                    />
                    <x-input-error :messages="$errors->get('admin_email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="Temporary Password" />
                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1"
                        required
                        autocomplete="new-password"
                        placeholder="Set an initial password"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="Confirm Password" />
                    <x-text-input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        class="mt-1"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm the password"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600 lg:rounded-[10px]"
                    >
                        Create School
                    </button>
                    <a href="{{ route('super-admin.schools.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-super-admin-layout>
