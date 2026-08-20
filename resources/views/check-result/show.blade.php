<x-auth-layout :title="'Check Result - '.$school->name" simple>
    <x-auth-card>
        <h2 class="text-xl font-bold text-gray-900">Check Your Result</h2>
        <p class="mt-1 text-sm text-gray-600">{{ $school->name }} &middot; enter your result-checking PIN and admission number.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-[5px] bg-red-50 p-4 text-sm text-red-700 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('check-result.verify', $school) }}" class="mt-6 space-y-5">
            @csrf

            <x-text-field
                name="code"
                label="Result-Checking PIN"
                icon="M15.5 8.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM13 11l-6.5 6.5"
                placeholder="XXXX-XXXX-XXXX"
                required
                autofocus
            />

            <x-text-field
                name="admission_number"
                label="Admission Number"
                icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                placeholder="e.g. ADM-0001"
                required
            />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
            >
                Check Result
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">Each PIN works for one student only, up to 4 times. Contact your school if you don't have a PIN.</p>
    </x-auth-card>
</x-auth-layout>
