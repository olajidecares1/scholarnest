<x-dashboard-layout :page-title="$assignment->title" :page-subtitle="$assignment->class_name.' · '.$assignment->subject">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('assignments.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Assignments
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $assignment->title }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $assignment->class_name }} &middot; {{ $assignment->subject }} &middot; Due {{ $assignment->due_date->format('M j, Y') }} &middot; Max {{ $assignment->max_score }}
                    </p>
                    @if ($assignment->description)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $assignment->description }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('assignments.destroy', $assignment) }}" onsubmit="return confirm('Delete {{ $assignment->title }}? All submissions will be removed.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete Assignment</button>
                </form>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Student Submissions</h3>
                <p class="field-hint mt-0.5">Track submission status and record scores per student.</p>
            </div>

            <form method="POST" action="{{ route('assignments.submissions.store', $assignment) }}">
                @csrf

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Student</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                                <th class="px-6 py-3 font-semibold">Score / {{ $assignment->max_score }}</th>
                                <th class="px-6 py-3 font-semibold">Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($students as $student)
                                @php $current = $existing->get($student->id); @endphp
                                <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($student->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                            @else
                                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="grid max-w-sm grid-cols-4 gap-1.5">
                                            @foreach ($statusOptions as $status)
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="submissions[{{ $student->id }}][status]" value="{{ $status->value }}" class="peer sr-only" @checked(($current->status ?? \App\Enums\SubmissionStatus::NotSubmitted) === $status)>
                                                    <span class="block rounded-[6px] border border-gray-300 px-1.5 py-1.5 text-center text-[11px] font-semibold text-gray-500 transition-colors duration-150 peer-checked:border-transparent peer-checked:{{ $status->badgeClasses() }} dark:border-gray-600 dark:text-gray-400">
                                                        {{ $status->label() }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <input
                                            type="number"
                                            name="submissions[{{ $student->id }}][score]"
                                            value="{{ $current?->score }}"
                                            min="0"
                                            max="{{ $assignment->max_score }}"
                                            step="0.01"
                                            class="w-24"
                                        >
                                    </td>
                                    <td class="px-6 py-3">
                                        <input
                                            type="text"
                                            name="submissions[{{ $student->id }}][feedback]"
                                            value="{{ $current?->feedback }}"
                                            placeholder="Optional feedback"
                                            class="w-56"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $assignment->class_name }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex justify-end border-t border-gray-100 p-6 dark:border-gray-700">
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                            Save Submissions
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-dashboard-layout>
