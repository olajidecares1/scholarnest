{{-- Regenerate admission numbers and Staff IDs from the School Code.

     Asks first. Each card says how many numbers will change, and nothing is
     saved until its button is pressed. Numbers typed by hand are kept unless
     the admin ticks the box to replace those too. See
     App\Services\IdentifierRegenerator. --}}
<x-dashboard-layout page-title="Regenerate ID Numbers" page-subtitle="Give existing students and staff numbers that use your School Code.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">School Code: {{ $school->school_code }}</h2>
            <small class="field-hint mt-0.5 block">
                New numbers are given in order of registration, oldest first, in the same format new registrations get.
                Every previous number is kept on record, so old receipts and report cards can still be traced, and it still works for signing in.
                Results, fees, attendance and everything else linked to a student or staff member show the new number straight away.
            </small>
            @unless ($school->auto_generate_admission_numbers || $school->auto_generate_staff_ids)
                <p class="mt-3 rounded-[8px] bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    Automatic generation is switched off in Settings, so new registrations will still need a number typed in.
                    Switch it on under Identification Numbers to keep new numbers in the same format.
                </p>
            @endunless
        </div>

        @foreach ([
            ['type' => 'admission', 'counts' => $students, 'noun' => 'student', 'nouns' => 'students', 'number' => 'admission number', 'a_number' => 'an admission number', 'numbers' => 'admission numbers', 'sample' => $sampleAdmission, 'button' => 'Regenerate Admission Numbers', 'title' => 'Students'],
            ['type' => 'staff', 'counts' => $staff, 'noun' => 'staff member', 'nouns' => 'staff members', 'number' => 'Staff ID', 'a_number' => 'a Staff ID', 'numbers' => 'Staff IDs', 'sample' => $sampleStaff, 'button' => 'Regenerate Staff IDs', 'title' => 'Staff'],
        ] as $card)
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                 x-data="{ includeManual: false, generated: {{ $card['counts']['generated'] }}, manual: {{ $card['counts']['manual'] }} }">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $card['title'] }}</h2>

                @if ($card['counts']['total'] === 0)
                    <small class="field-hint mt-0.5 block">No {{ $card['nouns'] }} registered yet. New {{ $card['numbers'] }} will use school code {{ $school->school_code }} automatically.</small>
                @else
                    <form method="POST" action="{{ route('settings.identifiers.regenerate') }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="type" value="{{ $card['type'] }}">

                        <p class="text-sm text-gray-700 dark:text-gray-200" data-regenerate-question="{{ $card['type'] }}">
                            <strong x-text="includeManual ? generated + manual : generated">{{ $card['counts']['generated'] }}</strong>
                            <span x-text="(includeManual ? generated + manual : generated) === 1 ? '{{ $card['noun'] }} already has {{ $card['a_number'] }}.' : '{{ $card['nouns'] }} already have {{ $card['numbers'] }}.'">{{ $card['counts']['generated'] === 1 ? $card['noun'].' already has '.$card['a_number'].'.' : $card['nouns'].' already have '.$card['numbers'].'.' }}</span>
                            Regenerate them using school code <strong>{{ $school->school_code }}</strong>?
                        </p>
                        <small class="field-hint block">New {{ $card['numbers'] }} look like <strong>{{ $card['sample'] }}</strong>.</small>

                        @if ($card['counts']['manual'] > 0)
                            <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="include_manual" value="1" x-model="includeManual" class="mt-0.5 w-4 text-blue-600">
                                <span>
                                    Also replace the {{ $card['counts']['manual'] }} {{ $card['counts']['manual'] === 1 ? $card['number'].' that was' : $card['numbers'].' that were' }} typed by hand
                                    <small class="block text-xs text-gray-500 dark:text-gray-400">Left unticked, numbers typed by hand are kept exactly as they are.</small>
                                </span>
                            </label>
                        @endif

                        @if ($card['counts']['generated'] === 0 && $card['counts']['manual'] > 0)
                            <small class="field-hint block" x-show="! includeManual">Every {{ $card['number'] }} was typed by hand, so there is nothing to regenerate unless you tick the box above.</small>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                    x-bind:disabled="(includeManual ? generated + manual : generated) === 0"
                                    class="btn rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50">
                                <i class="fa-solid fa-arrows-rotate btn-icon" aria-hidden="true"></i> {{ $card['button'] }}
                            </button>
                            <a href="{{ route('settings.index') }}" class="text-sm font-semibold text-gray-600 hover:underline dark:text-gray-300">Not now</a>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-dashboard-layout>
