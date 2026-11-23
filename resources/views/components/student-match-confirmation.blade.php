@props([
    'candidates' => [],
    'data' => [],
    'hasPhoto' => false,
    'autoGenerate' => false,
    'confirmUrl',
    'cancelUrl',
    'showLinks' => true,
    'cancelMethod' => 'DELETE',
])

{{-- "Is this the same student?" The confirmation step shared by the School
     Admin's Add Student form and a Class Teacher's My Class form.

     A name alone is not proof, so whoever is registering decides: update the
     existing record (admission number, results, attendance and payments all
     kept), or register a different child with the same name. Nothing is
     preselected. A record the user may not update (a Class Teacher looking at
     a pupil in another class) is shown, with the reason, but cannot be
     chosen. --}}
@php
    $labels = [
        'first_name' => 'First name', 'last_name' => 'Surname', 'gender' => 'Gender',
        'date_of_birth' => 'Date of birth', 'class_name' => 'Class', 'house' => 'House',
        'guardian_name' => 'Parent/guardian', 'guardian_phone' => 'Guardian phone',
        'guardian_email' => 'Guardian email', 'phone' => 'Student phone', 'email' => 'Student email',
        'address' => 'Address', 'admission_date' => 'Admission date', 'admission_number' => 'Admission number',
    ];
    $show = function (string $field, $value) {
        if (blank($value)) {
            return '—';
        }
        if ($field === 'gender') {
            return \App\Enums\Gender::tryFrom((string) $value)?->label() ?? $value;
        }
        if (in_array($field, ['date_of_birth', 'admission_date'], true)) {
            try { return \Carbon\Carbon::parse($value)->format('j M Y'); } catch (\Throwable) { return $value; }
        }
        return $value;
    };
    $entered = collect($labels)->filter(fn ($label, $field) => filled($data[$field] ?? null) && ! ($field === 'admission_number' && $autoGenerate));
@endphp

    <div class="mx-auto max-w-4xl space-y-6">
        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-[10px] border-2 border-amber-400 bg-amber-50 p-5 dark:border-amber-500/70 dark:bg-amber-900/20">
            <h2 class="flex items-center gap-2 text-base font-extrabold text-amber-900 dark:text-amber-200">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                Nothing has been saved yet
            </h2>
            <small class="mt-1 block text-sm leading-relaxed text-amber-900 dark:text-amber-200">
                {{ count($candidates) === 1 ? 'A student/pupil' : count($candidates).' students/pupils' }} with this name {{ count($candidates) === 1 ? 'is' : 'are' }} already on your records.
                Two different children can share a name, so please check the details below and tell us which this is.
                If it is the same child, their existing record is updated (for example, moved to the correct class). Their admission number, results, attendance and payments stay exactly as they are, and no second record is created.
            </small>
        </section>

        <section class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">What you just entered</h3>
            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($entered as $field => $label)
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="break-words font-medium text-gray-900 dark:text-gray-100">{{ $show($field, $data[$field]) }}</dd>
                    </div>
                @endforeach
                @if ($hasPhoto)
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Photograph</dt><dd class="font-medium text-gray-900 dark:text-gray-100">Attached</dd></div>
                @endif
            </dl>
        </section>

        <form method="POST" action="{{ $confirmUrl }}" class="space-y-4" x-data="{ choice: @js(old('choice', '')) }">
            @csrf

            @foreach ($candidates as $candidate)
                @php $notice = $candidate['notice']; @endphp
                @php $locked = $candidate['locked'] ?? null; @endphp
                <label @class(['block rounded-[10px] border-2 bg-white p-5 shadow-sm transition dark:bg-gray-800', 'cursor-pointer' => ! $locked, 'opacity-80' => $locked])
                    :class="choice === 'update:{{ $candidate['student_id'] }}' ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-900' : 'border-gray-200 dark:border-gray-700'">
                    <div class="flex items-start gap-3">
                        <input type="radio" name="choice" value="update:{{ $candidate['student_id'] }}" x-model="choice" class="mt-1 h-5 w-5 shrink-0" @disabled($locked)>
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-extrabold text-gray-900 dark:text-white">
                                @if ($locked)
                                    {{ $notice['full_name'] }} is already registered.
                                @else
                                    Yes, this is {{ $notice['full_name'] }}. Update their existing record.
                                @endif
                            </p>
                            @if ($locked)
                                <p class="mt-1 rounded-[8px] bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">{{ $locked }}</p>
                            @endif
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                Admission number
                                <span class="rounded-[6px] bg-gray-100 px-2 py-0.5 font-mono font-bold text-gray-900 dark:bg-gray-900 dark:text-white">{{ $notice['admission_number'] }}</span>
                                stays the same.
                            </p>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($candidate['signals'] as $signal)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ ucfirst($signal) }}</span>
                                @endforeach
                                @foreach ($candidate['warnings'] as $warning)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ ucfirst($warning) }}</span>
                                @endforeach
                            </div>

                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 rounded-[8px] bg-gray-50 p-4 text-sm dark:bg-gray-900/40 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($notice['details'] as $label => $value)
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                        <dd class="break-words font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($locked)
                            @elseif ($candidate['changes'] !== [])
                                <div class="mt-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">What will change</p>
                                    <ul class="mt-1 space-y-1 text-sm">
                                        @foreach ($candidate['changes'] as $label => [$from, $to])
                                            @php $field = array_search($label, $labels, true) ?: ''; @endphp
                                            <li class="text-gray-800 dark:text-gray-200">
                                                <span class="font-semibold">{{ $label }}:</span>
                                                <span class="text-gray-500 line-through">{{ $show($field, $from) }}</span>
                                                <i class="fa-solid fa-arrow-right mx-1 text-xs text-gray-400" aria-hidden="true"></i>
                                                <span class="font-semibold text-blue-700 dark:text-blue-300">{{ $show($field, $to) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <small class="mt-3 block text-sm text-gray-600 dark:text-gray-400">The record already has these details; nothing would change.</small>
                            @endif

                            @if ($showLinks)
                                <a href="{{ $notice['show_url'] }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:text-blue-700">
                                    <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Open this record in a new tab
                                </a>
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach

            <label class="block cursor-pointer rounded-[10px] border-2 bg-white p-5 shadow-sm transition dark:bg-gray-800"
                :class="choice === 'new' ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-900' : 'border-gray-200 dark:border-gray-700'">
                <div class="flex items-start gap-3">
                    <input type="radio" name="choice" value="new" x-model="choice" class="mt-1 h-5 w-5 shrink-0">
                    <div>
                        <p class="text-base font-extrabold text-gray-900 dark:text-white">No, this is a different child with the same name.</p>
                        <small class="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                            Register them as a new student/pupil with {{ $autoGenerate ? 'a new admission number' : 'the admission number you entered' }}. The existing {{ count($candidates) === 1 ? 'record is' : 'records are' }} not changed.
                        </small>
                    </div>
                </div>
            </label>

            <div class="flex flex-wrap justify-end gap-2">
                <button type="submit" form="cancel-registration" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                    Cancel, save nothing
                </button>
                <button type="submit" :disabled="choice === ''" class="btn rounded-[8px] bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                    <i class="fa-solid fa-check btn-icon" aria-hidden="true"></i>
                    <span x-text="choice === 'new' ? 'Register as a new student' : (choice === '' ? 'Choose an option above' : 'Update the existing record')">Confirm</span>
                </button>
            </div>
        </form>

        <form id="cancel-registration" method="POST" action="{{ $cancelUrl }}">
            @csrf
            @if ($cancelMethod !== 'POST')
                @method($cancelMethod)
            @endif
        </form>
    </div>
