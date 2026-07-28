<x-super-admin-layout page-title="Add School" page-subtitle="Manually onboard a new school and its admin account.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.schools.store') }}" class="space-y-5">
                @csrf

                <x-text-field
                    id="school_name"
                    name="school_name"
                    label="School Name"
                    icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11"
                    helper="The official name schools and admins will see across the platform."
                    :value="old('school_name')"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="e.g. Bright Future Academy"
                />

                <x-text-field
                    id="admin_name"
                    name="admin_name"
                    label="Admin Full Name"
                    icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                    helper="The person who will manage this school's account."
                    :value="old('admin_name')"
                    required
                    autocomplete="name"
                    placeholder="e.g. Jane Doe"
                />

                <x-text-field
                    id="admin_email"
                    name="admin_email"
                    label="Admin Email"
                    type="email"
                    icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                    helper="Used to sign in and receive account notifications."
                    :value="old('admin_email')"
                    required
                    autocomplete="email"
                    placeholder="admin@school.com"
                />

                <x-password-field
                    id="password"
                    name="password"
                    label="Temporary Password"
                    helper="Share this with the admin securely; they can change it after signing in."
                    required
                    autocomplete="new-password"
                    placeholder="Set an initial password"
                />

                <x-password-field
                    id="password_confirmation"
                    name="password_confirmation"
                    label="Confirm Password"
                    helper="Re-enter the password exactly as above."
                    required
                    autocomplete="new-password"
                    placeholder="Confirm the password"
                />

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
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
