{{-- The teacher's diary: what was taught, week by week.

     The subject list is the teacher's own assignments rather than a text box,
     so an entry cannot be filed under a class or subject they do not teach.
     The server re-checks the pair on submit; this is the convenience, not the
     boundary. --}}
<x-staff-layout page-title="Diary" page-subtitle="Record the topic you taught each week, subject by subject.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        @if ($assignments->isEmpty())
            {{-- Not an empty dropdown. A teacher with no subject assignments
                 cannot write a diary entry, and the reason is something only
                 the school office can put right. --}}
            <div class="flex items-start gap-3 rounded-[10px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-900/20">
                <i class="fa-solid fa-folder-open mt-0.5 text-amber-600 dark:text-amber-400"></i>
                <div>
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-300">No subjects assigned to you yet</h2>
                    <p class="mt-1 text-[12.5px] leading-[1.6] text-amber-800 dark:text-amber-400">
                        Your diary lists the subjects you teach. Ask your school office to assign you to a class
                        and subject, and they will appear here.
                    </p>
                </div>
            </div>
        @else
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <i class="fa-solid fa-folder-plus text-primary-500"></i>
                    Record this week's topic
                </h2>
                <p class="field-hint mt-0.5">
                    Writing again for a week you have already recorded replaces that entry, and sends it back to
                    your school to be reviewed.
                </p>

                <form method="POST" action="{{ route('staff.diary.store', $school) }}" class="mt-4 space-y-2">
                    @csrf

                    <div>
                        <label for="assignment" class="field-label">Subject &amp; Class</label>
                        <select id="assignment" name="assignment" required class="mt-1">
                            <option value="">Choose a subject you teach…</option>
                            @foreach ($assignments as $assignment)
                                @php($value = $assignment['class_name'].'|'.$assignment['subject'])
                                <option value="{{ $value }}" @selected(old('assignment') === $value)>
                                    {{ $assignment['subject'] }} &mdash; {{ $assignment['class_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('assignment')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div>
                            <label for="session" class="field-label">Academic Year</label>
                            <select id="session" name="session" required class="mt-1">
                                @foreach ($sessions as $session)
                                    <option value="{{ $session }}" @selected(old('session') === $session)>{{ $session }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="term" class="field-label">Term</label>
                            <select id="term" name="term" required class="mt-1">
                                @foreach ($terms as $term)
                                    <option value="{{ $term->value }}" @selected(old('term') === $term->value)>{{ $term->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="week_number" class="field-label">Week</label>
                            <select id="week_number" name="week_number" required class="mt-1">
                                @foreach ($weeks as $week)
                                    <option value="{{ $week }}" @selected((int) old('week_number') === $week)>Week {{ $week }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <x-textarea-field
                        name="topic"
                        label="Topic Taught"
                        rows="3"
                        required
                        :value="old('topic')"
                        placeholder="e.g. Quadratic equations: factorisation and the difference of two squares."
                    />

                    <div class="flex justify-end pt-1">
                        <button type="submit" class="flex items-center gap-2 rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            Submit Entry
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Your entries</h2>
                <p class="field-hint mt-0.5">Whether your school has read each one, and when.</p>
            </div>

            @forelse ($entries as $entry)
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-6 last:border-0 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ $entry->subject }} &middot; {{ $entry->class_name }}
                        </p>
                        <p class="field-hint mt-0.5">
                            Week {{ $entry->week_number }} &middot; {{ $entry->term->label() }} &middot; {{ $entry->session }}
                        </p>
                        <p class="mt-2 whitespace-pre-line text-[13px] leading-[1.6] text-gray-700 dark:text-gray-300">{{ $entry->topic }}</p>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $entry->status->badgeClasses() }}">
                            <i class="fa-solid {{ $entry->status->icon() }} text-[10px]"></i>
                            {{ $entry->status->label() }}
                        </span>

                        @if ($entry->hasBeenSeen())
                            <p class="field-hint mt-1">
                                by {{ $entry->seenBy?->name ?? 'your school' }}
                                &middot; {{ $entry->seen_at?->diffForHumans() }}
                            </p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-6 text-sm text-gray-500 dark:text-gray-400">You have not recorded any topics yet.</p>
            @endforelse

            @if ($entries->hasPages())
                <div class="p-6">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</x-staff-layout>
