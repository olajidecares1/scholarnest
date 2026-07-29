<x-dashboard-layout :page-title="$examBody->name" page-subtitle="Practice exams available for this exam body.">
    <div class="space-y-6">
        <a href="{{ route('cbt-practice.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT Practice
        </a>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6">
                <h2 class="text-sm font-bold text-gray-900">{{ $examBody->name }} Exams</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ $exams->count() }} exam(s) available for students to practice.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Year</th>
                            <th class="px-6 py-3 font-semibold">Duration</th>
                            <th class="px-6 py-3 font-semibold">Pass Mark</th>
                            <th class="px-6 py-3 font-semibold">Questions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($exams as $exam)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-6 py-3 font-semibold text-gray-900">{{ $exam->subject->name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $exam->year }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $exam->duration_minutes }} mins</td>
                                <td class="px-6 py-3 text-gray-600">{{ $exam->pass_mark }}%</td>
                                <td class="px-6 py-3 text-gray-600">{{ $exam->questions_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No exams have been added for {{ $examBody->name }} yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
