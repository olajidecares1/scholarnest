<x-auth-layout :simple="true" :title="'Portal · '.$school->name">
    <x-auth-card>
        <div class="text-center">
            @if ($school->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}" class="mx-auto h-16 w-16 rounded-[10px] object-cover shadow-md">
            @else
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-[10px] bg-primary-600 text-2xl text-white shadow-md"><i class="fa-solid fa-school"></i></span>
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
        </div>
    </x-auth-card>
</x-auth-layout>
