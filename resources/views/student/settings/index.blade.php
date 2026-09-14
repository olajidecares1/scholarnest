{{-- A student's own details are the school's record, not theirs to edit.

     There is no profile form here and no route behind one: a student who could
     change their own email or phone could change where their school's messages
     go. What they can do is ask, and the school decides, which is what the
     request card below is for. --}}
<x-student-layout page-title="Settings" page-subtitle="Your account details">
    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                @if ($student->photoUrl())
                    <img src="{{ $student->photoUrl() }}" class="h-16 w-16 rounded-full object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ Str::of($student->first_name)->substr(0, 1)->upper() }}</span>
                @endif
                <div class="min-w-0">
                    <h2 class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $student->fullName() }}</h2>
                    <p class="field-hint">{{ $student->class_name }}</p>
                </div>
            </div>

            <dl class="mt-5 divide-y divide-gray-100 text-sm dark:divide-gray-700">
                @foreach ([
                    ['Admission Number', $student->admission_number],
                    ['Class', $student->class_name],
                    ['Email', $student->email],
                    ['Phone', $student->phone],
                    ['Address', $student->address],
                    ['House', $student->house],
                ] as [$label, $value])
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-right font-semibold text-gray-900 dark:text-white">{{ $value ?: 'N/A' }}</dd>
                    </div>
                @endforeach
            </dl>

            <p class="field-hint mt-4">
                These are your school&rsquo;s records. If anything here is wrong, ask them to correct it
                using the form below.
            </p>
        </div>

        {{-- No password form, and no route behind one. The school office sets
             passwords; a portal that also offered a self-service change would
             be a second door into the same lock, one the school could not see
             through. --}}
        <div class="flex items-start gap-3 rounded-[10px] border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <i class="fa-solid fa-lock mt-0.5 text-gray-400"></i>
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Password</h2>
                <p class="field-hint mt-0.5">
                    Your password is set by your school office. If you have forgotten it, ask them for a new
                    one &mdash; they can issue it straight away.
                </p>
            </div>
        </div>

        <x-profile-change-request-card
            :action="route('student.profile-change-requests.store', $school)"
            :fields="$protectedFields"
            :requests="$changeRequests"
        />
    </div>
</x-student-layout>
