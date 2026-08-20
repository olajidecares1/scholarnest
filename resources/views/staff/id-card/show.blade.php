<x-staff-layout page-title="ID Card" page-subtitle="Your school identification card">
    <div class="mx-auto max-w-xl" x-data="{}">
        <div class="rounded-[10px] border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 6.5h16a1 1 0 011 1V17a1 1 0 01-1 1H4a1 1 0 01-1-1V7.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="8" cy="12" r="1.75" stroke="currentColor" stroke-width="1.5" />
                    <path d="M12.5 10.5h5M12.5 13.5h5M5.5 16.2c.3-1.2 1.3-2 2.5-2s2.2.8 2.5 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
            </span>
            <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ $staff->fullName() }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $staff->staff_number }}</p>

            <button
                type="button"
                @click="$store.idCardPreview.openPreview('{{ route('staff.id-card.preview', $school) }}', '', '')"
                class="mt-6 inline-flex items-center justify-center rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700"
            >
                View ID Card
            </button>

            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">This is a view-only preview. Printing and downloading are handled by your school office.</p>
        </div>
    </div>

    <x-id-card-preview-modal view-only />
</x-staff-layout>
