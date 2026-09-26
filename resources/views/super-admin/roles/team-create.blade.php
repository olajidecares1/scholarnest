<x-super-admin-layout page-title="Add Team Member" page-subtitle="Grant another admin AkademicNest Team access.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.roles.team.store') }}" class="space-y-2">
                @csrf

                <x-text-field
                    id="name"
                    name="name"
                    label="Full Name"
                    icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7"
                    helper="The person you're granting AkademicNest Team access to."
                    :value="old('name')"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="e.g. Jane Doe"
                />

                <x-text-field
                    id="email"
                    name="email"
                    label="Email"
                    type="email"
                    icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
                    helper="Used to sign in and receive account notifications."
                    :value="old('email')"
                    required
                    autocomplete="email"
                    placeholder="admin@akademicanest.com"
                />

                <x-select-field
                    id="admin_role_id"
                    name="admin_role_id"
                    label="Role"
                    icon="M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"
                    helper="Restricts what this team member can access. Leave as Full Access for none."
                    :options="collect(['' => 'Full Access (no restrictions)'])->union($roles->pluck('name', 'id'))"
                    :selected="old('admin_role_id')"
                />

                <x-password-field
                    id="password"
                    name="password"
                    label="Temporary Password"
                    helper="Share this with them securely; they can change it after signing in."
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
                        class="btn flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
                    >
                        Add Team Member
                    </button>
                    <a href="{{ route('super-admin.roles.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-super-admin-layout>
