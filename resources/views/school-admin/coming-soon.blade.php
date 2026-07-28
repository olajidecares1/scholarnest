<x-dashboard-layout :page-title="$label" :page-subtitle="$description">
    <div class="flex min-h-[60vh] items-center justify-center">
        <div class="max-w-md text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-[10px] bg-blue-50 text-blue-600">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 7v5l3.2 1.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6" />
                </svg>
            </span>
            <h2 class="mt-4 text-xl font-bold text-gray-900">{{ $label }} is coming soon</h2>
            <p class="mt-2 text-sm text-gray-600">{{ $description }}</p>
            <p class="mt-1 text-sm text-gray-600">This module is on our roadmap and isn&rsquo;t available yet.</p>
            <a
                href="{{ route('dashboard') }}"
                class="mt-6 inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
            >
                Back to Dashboard
            </a>
        </div>
    </div>
</x-dashboard-layout>
