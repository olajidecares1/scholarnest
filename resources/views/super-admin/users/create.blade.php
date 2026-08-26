<x-super-admin-layout page-title="Add Admin" page-subtitle="Add another admin account to an existing school.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.users.store') }}" class="space-y-2">
                @csrf

                <x-select-field
                    id="school_id"
                    name="school_id"
                    label="School"
                    icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11"
                    helper="Which school will this admin manage?"
                    :options="$schools->pluck('name', 'id')"
                    :selected="old('school_id')"
                    placeholder="Select a school"
                    required
                />

                <x-text-field
                    id="name"
                    name="name"
                    label="Admin Full Name"
                    icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                    helper="The person who will manage this school's account."
                    :value="old('name')"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="e.g. Jane Doe"
                />

                <x-text-field
                    id="email"
                    name="email"
                    label="Admin Email"
                    type="email"
                    icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                    helper="Used to sign in and receive account notifications."
                    :value="old('email')"
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
                        Add Admin
                    </button>
                    <a href="{{ route('super-admin.users.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-super-admin-layout>
