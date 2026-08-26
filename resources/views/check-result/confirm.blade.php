{{-- Step two of two: confirm the pupil, then enter the token.

     Showing who was found before the token is spent is the point of splitting
     the flow: a parent who has typed the wrong admission number sees it here,
     rather than after burning a use on somebody else's record.

     The pupil on this page came from the session, not the address - there is
     no id in the URL to change. --}}
<x-auth-layout :title="'Check Result - '.$school->name" :school="$school" simple>
    <x-auth-card>
        @php
            $onSchoolResultLink = request()->routeIs('school-result.*');
            $schoolValue = $onSchoolResultLink ? $school->result_link_slug : $school;
            $verifyUrl = $onSchoolResultLink
                ? route('school-result.verify', ['school' => $schoolValue])
                : route('check-result.verify', $school);
            $backUrl = $onSchoolResultLink
                ? route('school-result.show', ['school' => $schoolValue])
                : route('check-result.show', $school);
        @endphp

        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-primary-600">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-[10px] text-white">2</span>
            Step 2 of 2
        </div>

        <h2 class="mt-3 text-xl font-bold text-gray-900">Confirm &amp; Enter Token</h2>
        <p class="mt-1 text-sm text-gray-600">Check that this is the right pupil, then enter the exam token.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-[5px] bg-red-50 p-4 text-sm text-red-700 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Name, photo and class, straight from the pupil's own record. --}}
        <div class="mt-5 flex items-center gap-4 rounded-[10px] border border-gray-200 bg-gray-50 p-4">
            @if ($student->photoUrl())
                <img
                    src="{{ $student->photoUrl() }}"
                    alt="{{ $student->fullName() }}"
                    class="h-16 w-16 shrink-0 rounded-full object-cover ring-2 ring-white"
                >
            @else
                {{-- A placeholder rather than a stand-in face. Showing any real
                     photograph here that was not this pupil's would be worse
                     than showing none. --}}
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700 ring-2 ring-white">
                    {{ mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1)) }}
                </div>
            @endif

            <div class="min-w-0">
                <p class="truncate text-base font-bold text-gray-900">{{ $student->fullName() }}</p>
                <p class="mt-0.5 text-sm text-gray-600">{{ $student->class_name }}</p>
                <p class="mt-0.5 font-mono text-xs text-gray-500">{{ $student->admission_number }}</p>
            </div>
        </div>

        <p class="mt-2 text-center text-xs text-gray-500">
            Not the right pupil? <a href="{{ $backUrl }}" class="font-semibold text-primary-600 hover:text-primary-700">Start again</a>.
        </p>

        <form method="POST" action="{{ $verifyUrl }}" class="mt-5 space-y-2">
            @csrf

            <x-text-field
                name="code"
                label="Exam Token"
                icon="M15.5 8.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM13 11l-6.5 6.5"
                placeholder="15-character exam token"
                maxlength="20"
                required
                autofocus
            />

            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-6 py-3 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition hover:bg-primary-600"
            >
                Check Result
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </form>
    </x-auth-card>
</x-auth-layout>
