<x-auth-layout :simple="true" :header="false" :title="'Portal · '.$school->name">
    <x-auth-card>
        @php
            // Which doors this school actually has.
            //
            // Every plan gets the School Admin and the Staff/Teacher portals -
            // the staff portal is where attendance and marks are entered, which
            // is core academic work rather than a premium extra.
            //
            // The Student and Parent portals are Standard and Exclusive only. A
            // Basic school has no accounts for families at all, so listing
            // those two here would offer a Basic parent a door that opens onto
            // the "locked" page. Parents on Basic are not shut out of results
            // though - they use a result token, which needs no account, so that
            // is what takes the place of those two entries.
            $hasFamilyPortals = $school->hasPlanAccess(\App\Enums\PlanKey::Standard, \App\Enums\PlanKey::Exclusive);
        @endphp

        <div class="text-center">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="mx-auto h-20 w-20 rounded-[10px] object-cover shadow-md">
            @else
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-[10px] bg-primary-600 text-2xl text-white shadow-md"><i class="fa-solid fa-school"></i></span>
            @endif
            <h1 class="mt-3 text-xl font-bold text-gray-900">{{ $school->name }}</h1>
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-500">Portal</p>
            <p class="mt-2 text-sm text-gray-500">Choose how you'd like to sign in.</p>
        </div>

        <div class="mt-6 space-y-3">
            <a href="{{ $school->publicUrl('portal.admin.login', ['token' => $school->portal_admin_token]) }}" class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                    <i class="fa-solid fa-user-gear"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-gray-900">School Admin</span>
                    <span class="block text-xs text-gray-500">Manage this school</span>
                </span>
                <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
            </a>

            <a href="{{ $school->publicUrl('staff.login', ['token' => $school->portal_staff_token]) }}" class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-gray-900">Staff / Teacher</span>
                    <span class="block text-xs text-gray-500">Teaching &amp; staff access</span>
                </span>
                <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
            </a>

            @if ($hasFamilyPortals)
                <a href="{{ $school->publicUrl('student.login', ['token' => $school->portal_student_token]) }}" class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                        <i class="fa-solid fa-user-graduate"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">Student</span>
                        <span class="block text-xs text-gray-500">Results, assignments &amp; more</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
                </a>

                <a href="{{ $school->publicUrl('guardian.login', ['token' => $school->portal_guardian_token]) }}" class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                        <i class="fa-solid fa-people-roof"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">Parent / Guardian</span>
                        <span class="block text-xs text-gray-500">Your child's information</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
                </a>
            @else
                <a href="{{ $school->resultLinkUrl() }}" class="group flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600">
                        <i class="fa-solid fa-file-circle-check"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">Check Result</span>
                        <span class="block text-xs text-gray-500">Enter the Result Token from your school &mdash; no account needed</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-colors duration-200 group-hover:text-primary-400"></i>
                </a>
            @endif
        </div>
    </x-auth-card>
</x-auth-layout>
