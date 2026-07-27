<x-super-admin-layout :page-title="$section['title']" :page-subtitle="$section['description']">
    <div class="mx-auto max-w-2xl">
        <div class="rounded-[5px] border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-[5px] bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                </svg>
            </span>

            <h2 class="mt-4 text-xl font-bold text-gray-900 dark:text-white">{{ $section['title'] }} — Coming Soon</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $section['description'] }}</p>

            @if (! empty($section['features']))
                <ul class="mx-auto mt-6 max-w-sm space-y-2 text-left">
                    @foreach ($section['features'] as $feature)
                        <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            @endif

            <a href="{{ route('super-admin.dashboard') }}" class="mt-8 inline-block text-sm font-semibold text-primary-500 hover:text-primary-600">&larr; Back to Dashboard</a>
        </div>
    </div>
</x-super-admin-layout>
