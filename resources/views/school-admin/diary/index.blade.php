{{-- The school reading its teachers' diaries.

     Marking an entry seen is the half of the loop a teacher can observe. A
     diary submitted into silence gives them no way to tell one that is being
     read from one that is not. --}}
<x-dashboard-layout page-title="Teacher Diary" page-subtitle="What each teacher taught, week by week.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($awaitingReview > 0)
            <div class="flex items-center gap-3 rounded-[5px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20 lg:rounded-[10px]">
                <i class="fa-solid fa-folder-open text-amber-600 dark:text-amber-400"></i>
                <p class="text-[13px] font-semibold text-amber-900 dark:text-amber-300">
                    {{ number_format($awaitingReview) }} {{ \Illuminate\Support\Str::plural('entry', $awaitingReview) }}
                    waiting to be reviewed.
                </p>
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" class="flex flex-wrap items-end gap-2 border-b border-gray-100 p-6 dark:border-gray-700">
                <div class="w-44">
                    <label for="filter_status" class="field-label">Status</label>
                    <select id="filter_status" name="status" onchange="this.form.submit()" class="mt-1">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-52">
                    <label for="filter_staff" class="field-label">Teacher</label>
                    <select id="filter_staff" name="staff_id" onchange="this.form.submit()" class="mt-1">
                        <option value="">All teachers</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(request('staff_id') == $teacher->id)>{{ $teacher->fullName() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <label for="filter_class" class="field-label">Class</label>
                    <select id="filter_class" name="class_name" onchange="this.form.submit()" class="mt-1">
                        <option value="">All classes</option>
                        @foreach ($classNames as $className)
                            <option value="{{ $className }}" @selected(request('class_name') === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="filter_term" class="field-label">Term</label>
                    <select id="filter_term" name="term" onchange="this.form.submit()" class="mt-1">
                        <option value="">All terms</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->value }}" @selected(request('term') === $term->value)>{{ $term->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-40">
                    <label for="filter_session" class="field-label">Academic Year</label>
                    <select id="filter_session" name="session" onchange="this.form.submit()" class="mt-1">
                        <option value="">All years</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session }}" @selected(request('session') === $session)>{{ $session }}</option>
                        @endforeach
                    </select>
                </div>

                @if (request()->hasAny(['status', 'staff_id', 'class_name', 'term', 'session']))
                    <a href="{{ route('diary.index') }}" class="pb-2 text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                @endif
            </form>

            @forelse ($entries as $entry)
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 p-6 last:border-0 dark:border-gray-700">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ $entry->subject }} &middot; {{ $entry->class_name }}
                        </p>
                        <p class="field-hint mt-0.5">
                            {{ $entry->teacher->fullName() }}
                            &middot; Week {{ $entry->week_number }}
                            &middot; {{ $entry->term->label() }}
                            &middot; {{ $entry->session }}
                            &middot; submitted {{ $entry->created_at->diffForHumans() }}
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
                                by {{ $entry->seenBy?->name ?? 'a school admin' }}
                                &middot; {{ $entry->seen_at?->diffForHumans() }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('diary.seen', $entry) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="flex items-center gap-1.5 rounded-[8px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                                    <i class="fa-solid fa-circle-check text-[11px]"></i>
                                    Mark as Seen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-6 text-sm text-gray-500 dark:text-gray-400">
                    No diary entries yet. They appear here as your teachers record what they have taught.
                </p>
            @endforelse

            @if ($entries->hasPages())
                <div class="p-6">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
