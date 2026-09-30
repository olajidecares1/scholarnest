<x-student-layout page-title="ID Card" page-subtitle="Your school identification card">
    <div class="mx-auto max-w-xl" x-data="{}">
        <div class="rounded-[10px] border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                <i class="fa-solid fa-id-card text-[24px] leading-none" aria-hidden="true"></i>
            </span>
            <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
            <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $student->admission_number }}</small>

            <button
                type="button"
                @click="$store.idCardPreview.openPreview('{{ route('student.id-card.preview', $school) }}', '', '')"
                class="btn mt-6 inline-flex items-center justify-center rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700"
            >
                <i class="fa-solid fa-id-card btn-icon" aria-hidden="true"></i> View ID Card
            </button>

            <small class="block mt-4 text-xs text-gray-400 dark:text-gray-500">This is a view-only preview. Printing and downloading are handled by your school office.</small>
        </div>
    </div>

    <x-id-card-preview-modal view-only />
</x-student-layout>
