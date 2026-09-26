<x-dashboard-layout :page-title="$examBody->name" page-subtitle="Practice exams available for this exam body.">
    <div class="space-y-6">
        <a href="{{ route('cbt-practice.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT Practice
        </a>

        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Grant Access to a Class</h2>
            <small class="field-hint mt-1">
                Students only see {{ $examBody->name }} automatically if their class matches its academic stage. Grant a specific class access here to override that (e.g. letting a Primary class try a Senior Secondary exam body).
            </small>

            <form method="POST" action="{{ route('cbt-practice.grants.store', $examBody) }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div class="w-56">
                    <x-text-field name="class_name" label="Class Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5" placeholder="e.g. Primary 4" required />
                </div>
                <button type="submit" class="btn h-11 rounded-[8px] bg-blue-600 px-4 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">Grant Access</button>
            </form>

            @if ($grants->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($grants as $grant)
                        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1.5 pl-3 pr-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            {{ $grant->class_name }}
                            <form method="POST" action="{{ route('cbt-practice.grants.destroy', $grant) }}" onsubmit="return confirm('Remove {{ $examBody->name }} access for {{ $grant->class_name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-full p-1 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                                    <i class="fa-solid fa-xmark text-[12px] leading-none" aria-hidden="true"></i>
                                </button>
                            </form>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $examBody->name }} Exams</h2>
                <small class="field-hint mt-0.5">{{ $exams->count() }} exam(s) available for students to practice.</small>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Year</th>
                            <th class="px-6 py-3 font-semibold">Duration</th>
                            <th class="px-6 py-3 font-semibold">Pass Mark</th>
                            <th class="px-6 py-3 font-semibold">Questions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($exams as $exam)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $exam->subject->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->year }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->duration_minutes }} mins</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->pass_mark }}%</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->questions_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No exams have been added for {{ $examBody->name }} yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
