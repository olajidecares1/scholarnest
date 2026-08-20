<x-student-layout page-title="Help & Support" page-subtitle="Get in touch with your school">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Frequently Asked Questions</h2>
            <div class="mt-4 space-y-4 text-sm">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">I forgot my portal password.</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Ask your class teacher or the school office to reset it for you from the school's admin dashboard.</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">My attendance or results look wrong.</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Please report this to your class teacher or the school office so they can review and correct it.</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">How do I update my contact details?</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">You can update your phone, email, and address from the Settings page. Guardian details can only be changed by the school office.</p>
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Contact {{ $school->name }}</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Phone</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $website?->contact_phone ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Email</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $website?->contact_email ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Address</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ $website?->contact_address ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>
</x-student-layout>
