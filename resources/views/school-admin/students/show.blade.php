<x-dashboard-layout :page-title="$student->fullName()" page-subtitle="Student Profile">
    <div class="space-y-6">
        <a href="{{ route('students.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Students
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center gap-4">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" class="h-20 w-20 rounded-full object-cover">
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                        {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $student->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $student->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $student->admission_number }}
                        @if ($student->class_name) &middot; {{ $student->class_name }} @endif
                        @if ($student->age()) &middot; {{ $student->age() }} years old @endif
                    </small>
                </div>
                <a href="{{ route('students.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Edit in Students List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Student Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Gender</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->gender->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Date of Birth</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->date_of_birth?->format('M j, Y') ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Class</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->class_name ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">House</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->house ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Admission Date</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->admission_date?->format('M j, Y') ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->phone ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->email ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $student->address ?? 'N/A' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Guardian Details</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_name ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_phone ?? 'N/A' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $student->guardian_email ?? 'N/A' }}</dd></div>
                </dl>

                @if ($student->notes)
                    <h3 class="mt-6 text-sm font-bold text-gray-900 dark:text-white">Notes</h3>
                    <small class="block mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $student->notes }}</small>
                @endif
            </div>
        </div>

        {{-- Only where there is a student portal to sign in to. A Basic school
             has none, so credentials for one would be an account that exists
             and cannot be used, and a card promising something the plan does
             not include. Its parents reach results by exam token instead. --}}
        @if ($school->hasPortalAccounts())
            <x-credential-share-banner />

        <x-login-details-card
            :share-url="$credentialShare['url']"
            :share-phone="$credentialShare['phone']"
                :action="route('students.credentials', $student)"
                :username="$student->admission_number"
                username-label="Admission Number"
                username-hint="Generated automatically and cannot be edited. This is what a student types into the student portal sign-in."
                :account-name="$student->fullName()"
                :has-signed-in="$student->last_login_at !== null"
                :last-sign-in="$student->last_login_at"
            />
        @else
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h3 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <i class="fa-solid fa-key text-gray-400"></i>
                    Login Details
                </h3>
                <small class="field-hint mt-1">
                    The Basic plan has no student portal, so students do not have sign-in accounts.
                    Parents reach {{ $student->first_name }}&rsquo;s results with an exam token instead &mdash;
                    generate one from <a href="{{ route('result-pins.index') }}" class="font-semibold text-primary-600 hover:underline">Generate Exam Token</a>.
                </small>
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Parent/Guardian Portal Access</h3>
            <small class="field-hint mt-1">Link a parent or guardian's email to give them read-only access to {{ $student->first_name }}'s portal. Linking an email already used for another child attaches this student to that same guardian account.</small>

            @if ($student->guardians->isNotEmpty())
                <div class="mt-4 space-y-3">
                    @foreach ($student->guardians as $guardian)
                        <div class="rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $guardian->name }}
                                        @if ($guardian->pivot->relationship)
                                            <span class="ml-1 text-xs font-normal text-gray-400">({{ $guardian->pivot->relationship }})</span>
                                        @endif
                                    </p>
                                    <small class="field-hint">
                                        {{ $guardian->email }}
                                        &middot; {{ $guardian->password ? 'Access Enabled' : 'No Password Set' }}
                                        @if ($guardian->last_login_at)
                                            &middot; Last logged in {{ $guardian->last_login_at->diffForHumans() }}
                                        @endif
                                    </small>
                                </div>
                                <form method="POST" action="{{ route('students.guardians.destroy', [$student, $guardian]) }}" onsubmit="return confirm('Unlink {{ $guardian->name }} from {{ $student->fullName() }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Unlink</button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('students.guardians.update-password', $guardian) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PUT')
                                <div class="w-56">
                                    <x-text-field name="password" label="Set Portal Password" type="password" icon="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4" helper="At least 6 characters." required />
                                </div>
                                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">{{ $guardian->password ? 'Reset Password' : 'Enable Portal Access' }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- The relationship can be made from either side. This is the
                 same picker the parent's own page uses, pointed the other
                 way: search the parents already on the system and link one.

                 Kept alongside the form below rather than replacing it,
                 because the two answer different questions, "this parent is
                 already here, find them" and "this parent is new". --}}
            <x-link-search-picker
                :search-url="route('students.guardian-candidates', $student)"
                :submit-url="route('students.guardians.link', $student)"
                field="guardian"
                label="Link an existing parent/guardian"
                placeholder="Search by name, email or Parent ID..."
                empty-text="No unlinked parents match that. Add a new one below."
            />

            <div class="mt-6 border-t border-gray-100 pt-6 dark:border-gray-700">
                <p class="text-sm font-bold text-gray-900 dark:text-white">Or add a new parent/guardian</p>

                <form method="POST" action="{{ route('students.guardians.store', $student) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @csrf
                    <x-text-field name="name" label="Guardian Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" required />
                    <x-text-field name="email" type="email" label="Guardian Email" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" required />
                    <x-text-field name="phone" label="Phone" icon="M5 4.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1 1 0 01-1 1A15 15 0 015 5.5a1 1 0 011-1z" helper="Optional." />
                    <x-text-field name="relationship" label="Relationship" icon="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" placeholder="e.g. Mother, Father, Guardian" helper="Optional." />
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Add &amp; Link Guardian</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
