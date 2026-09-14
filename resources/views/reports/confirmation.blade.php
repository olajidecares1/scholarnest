<x-auth-layout :title="'Report Submitted | ' . config('app.name')" simple>
    <x-auth-card>
        <div class="flex justify-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-[5px] bg-green-100 text-green-600 lg:rounded-[10px]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </div>

        <h2 class="mt-4 text-center text-xl font-bold text-gray-900">Report Submitted</h2>
        <p class="mt-1 text-center text-sm text-gray-600">
            Thank you for helping keep the AkademicNest community safe. Our team will review your report.
        </p>

        @if ($reference)
            <div class="mt-4 rounded-[5px] bg-primary-50 p-4 text-center lg:rounded-[10px]">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-600">Reference Number</p>
                <p class="mt-1 text-lg font-bold text-primary-700">{{ $reference }}</p>
            </div>
        @endif

        <p class="mt-6 text-center text-sm text-gray-600">
            <a href="{{ route('reports.create') }}" class="font-semibold text-primary-500 hover:text-primary-600">Submit another report</a>
        </p>
    </x-auth-card>
</x-auth-layout>
