<x-auth-layout :simple="true" :title="'Portal Unavailable · '.$school->name">
    <div class="w-full max-w-md rounded-[10px] border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4m0 4h.01M10.3 3.9L2.7 17a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </span>
        <h1 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">Staff Portal Unavailable</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $school->name }} doesn't currently have Staff Portal access on its plan. Please ask the school to upgrade to the Standard or Exclusive plan to unlock this feature.
        </p>
        <form method="POST" action="{{ route('staff.logout', $school) }}" class="mt-6">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Log Out</button>
        </form>
    </div>
</x-auth-layout>
