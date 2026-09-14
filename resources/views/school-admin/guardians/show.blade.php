<x-dashboard-layout page-title="{{ $guardian->name }}" page-subtitle="Guardian account & linked children">
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('guardians.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to Guardians
            </a>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $guardian->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                {{ $guardian->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Guardian Details</h3>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('guardians.toggle-active', $guardian) }}">
                        @csrf @method('POST')
                        <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            {{ $guardian->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('guardians.destroy', $guardian) }}" onsubmit="return confirm('Remove {{ $guardian->name }}? This unlinks all their children and cannot be undone.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete Guardian</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('guardians.update', $guardian) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @csrf @method('PUT')
                <x-text-field name="name" label="Full Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" :value="$guardian->name" required />
                <x-text-field name="email" type="email" label="Email" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" :value="$guardian->email" required />
                <x-text-field name="phone" label="Phone" icon="M5 4.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1 1 0 01-1 1A15 15 0 015 5.5a1 1 0 011-1z" :value="$guardian->phone" />
                <div class="sm:col-span-3">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Save Details</button>
                </div>
            </form>

        </div>

        {{-- The guardian pages are already Standard and Exclusive only, the
             whole guardians module is plan-gated, so there is nothing further
             to check here. --}}
        <x-credential-share-banner />

        <x-login-details-card
            :share-url="$credentialShare['url']"
            :share-phone="$credentialShare['phone']"
            :action="route('guardians.credentials', $guardian)"
            :username="$guardian->guardian_number"
            username-label="Parent ID"
            username-hint="Generated automatically and cannot be edited. This is what a parent or guardian types into the parent portal sign-in."
            :account-name="$guardian->name"
            :has-signed-in="$guardian->last_login_at !== null"
            :last-sign-in="$guardian->last_login_at"
        />

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Linked Children</h3>
            <p class="field-hint mt-1">Every child linked here is an existing student record. Nothing new is created by linking.</p>

            @if ($guardian->students->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($guardian->students as $child)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                            <a href="{{ route('students.show', $child) }}" class="flex items-center gap-3">
                                @if ($child->photoUrl())
                                    <img src="{{ $child->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                @else
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ Str::of($child->first_name)->substr(0, 1)->upper() }}{{ Str::of($child->last_name)->substr(0, 1)->upper() }}
                                    </span>
                                @endif
                                <span>
                                    <span class="block font-semibold text-gray-900 hover:text-blue-600 dark:text-white">
                                        {{ $child->fullName() }}
                                        @if ($child->pivot->relationship)
                                            <span class="ml-1 text-xs font-normal text-gray-400">({{ $child->pivot->relationship }})</span>
                                        @endif
                                    </span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $child->admission_number }}
                                        @if ($child->class_name) &middot; {{ $child->class_name }} @endif
                                    </span>
                                </span>
                            </a>
                            <form method="POST" action="{{ route('guardians.children.destroy', [$guardian, $child]) }}" onsubmit="return confirm('Unlink {{ $child->fullName() }} from {{ $guardian->name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Unlink</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No children linked yet.</p>
            @endif

            {{-- Class, then search, then select. A parent may have several
                 children here, and each is linked the same way. --}}
            <x-link-search-picker
                :search-url="route('guardians.link-candidates', $guardian)"
                :submit-url="route('guardians.children.store', $guardian)"
                field="student"
                label="Link a child to this parent/guardian"
                placeholder="Search by name or admission number..."
                :class-options="$classOptions"
                empty-text="No unlinked pupils match that."
            />
        </div>
    </div>
</x-dashboard-layout>
