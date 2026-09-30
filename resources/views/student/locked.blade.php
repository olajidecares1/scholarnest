<x-auth-layout :simple="true" :title="'Portal Unavailable · '.$school->name">
    <div class="w-full max-w-md rounded-[10px] border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
            <i class="fa-solid fa-triangle-exclamation text-[20px] leading-none" aria-hidden="true"></i>
        </span>
        <h1 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">Student Portal Unavailable</h1>
        <small class="block mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $school->name }} doesn't currently have Student Portal access on its plan. Please ask your school to upgrade to the Standard or Exclusive plan to unlock this feature.
        </small>
        <form method="POST" action="{{ route('student.logout', $school) }}" class="mt-6">
            @csrf
            <button type="submit" class="btn inline-flex items-center justify-center rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"><i class="fa-solid fa-right-from-bracket btn-icon" aria-hidden="true"></i> Log Out</button>
        </form>
    </div>
</x-auth-layout>
