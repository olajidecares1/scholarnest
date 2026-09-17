{{-- Step one of two: name the pupil.

     The token is not asked for here. It comes on the next screen, once the
     School ID has found somebody, so a parent confirms they are looking at
     their own child's record before spending a token on it. --}}
<x-auth-layout :title="'Check Result | '.$school->name" :school="$school" simple>
    <x-auth-card>
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-primary-600">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-[10px] text-white">1</span>
            Step 1 of 2
        </div>

        <h2 class="mt-3 text-xl font-bold text-gray-900">Check Result</h2>
        <p class="mt-1 text-sm text-gray-600">{{ $school->name }} &middot; enter the School ID or Admission Number to begin.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-[5px] bg-red-50 p-4 text-sm text-red-700 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // One form, three addresses: the school's own website
            // (/results on its subdomain), its shareable result link, and the
            // older default path. It posts back to whichever one was opened.
            $identifyUrl = \App\Support\ResultCheckRoutes::url('identify', $school);
        @endphp

        <form method="POST" action="{{ $identifyUrl }}" class="mt-6 space-y-2">
            @csrf
            <x-honeypot />

            <x-text-field
                name="admission_number"
                label="School ID / Admission Number"
                icon="M4 6.5h16a1 1 0 011 1V17a1 1 0 01-1 1H4a1 1 0 01-1-1V7.5a1 1 0 011-1z"
                placeholder="e.g. GRN-2026/2027-PRI-014"
                maxlength="64"
                required
                autofocus
            />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
            >
                Next
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">
            You will need the exam token your school gave you on the next screen.
        </p>
    </x-auth-card>
</x-auth-layout>
