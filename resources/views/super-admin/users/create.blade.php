<x-super-admin-layout page-title="Add Admin" page-subtitle="Add another admin account to an existing school.">
    <div class="mx-auto max-w-xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="POST" action="{{ route('super-admin.users.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="school_id" value="School" />
                    <select
                        id="school_id"
                        name="school_id"
                        required
                        class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-3 pl-4 pr-4 text-base text-gray-900 shadow-sm transition-colors duration-150 hover:border-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 dark:border-gray-600 dark:bg-gray-700 dark:text-white lg:rounded-[10px]"
                    >
                        <option value="" disabled selected>Select a school</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" value="Admin Full Name" />
                    <x-text-input
                        id="name"
                        name="name"
                        type="text"
                        class="mt-1"
                        :value="old('name')"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="e.g. Jane Doe"
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Admin Email" />
                    <x-text-input
                        id="email"
                        name="email"
                        type="email"
                        class="mt-1"
                        :value="old('email')"
                        required
                        autocomplete="email"
                        placeholder="admin@school.com"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
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
