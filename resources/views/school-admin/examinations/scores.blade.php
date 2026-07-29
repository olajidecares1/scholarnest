<x-dashboard-layout :page-title="'Scores · '.$subject->name" :page-subtitle="$examination->name.' · '.$examination->class_name">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('examinations.show', $examination) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to {{ $examination->name }}
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $subject->name }} &middot; Max Score {{ $subject->max_score }}</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Enter each student's score out of {{ $subject->max_score }}. Leave blank to skip a student.</p>
            </div>

            <form method="POST" action="{{ route('examinations.scores.store', $subject) }}">
                @csrf

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Student</th>
                                <th class="px-6 py-3 font-semibold">Score / {{ $subject->max_score }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($students as $student)
                                <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                                        <input
                                            type="number"
                                            name="scores[{{ $student->id }}]"
                                            value="{{ $existing->get($student->id)?->score }}"
                                            min="0"
                                            max="{{ $subject->max_score }}"
                                            step="0.01"
                                            class="h-10 w-28 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $examination->class_name }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex justify-end border-t border-gray-100 p-6 dark:border-gray-700">
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                            Save Scores
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-dashboard-layout>
