<x-dashboard-layout :page-title="$test->title" :page-subtitle="'By '.$test->staff->fullName().' · '.$test->subject.' · '.$test->class_name">
    <div class="space-y-6" x-data="{ editing: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('cbt-tests.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT Tests
        </a>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Status</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $test->status->label() }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Questions</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $test->questions->count() }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Attempts</p>
                <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $test->attempts->count() }}</p>
            </div>
        </div>

        {{-- Admin controls: edit meta + status/availability --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Manage Test</h2>
                <button type="button" @click="editing = !editing" class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400" x-text="editing ? 'Cancel' : 'Edit Details'"></button>
            </div>

            <form x-show="editing" style="display: none;" method="POST" action="{{ route('cbt-tests.update', $test) }}" class="mt-4 grid grid-cols-1 gap-4 border-b border-gray-100 pb-4 dark:border-gray-700 sm:grid-cols-2">
                @csrf @method('PUT')
                <x-text-field name="title" label="Title" :value="$test->title" required />
                <x-text-field name="subject" label="Subject" :value="$test->subject" required />
                <x-text-field name="class_name" label="Class" :value="$test->class_name" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-text-field name="duration_minutes" type="number" label="Duration (minutes)" :value="$test->duration_minutes" min="5" max="300" required />
                    <x-text-field name="pass_mark" type="number" label="Pass Mark (%)" :value="$test->pass_mark" min="1" max="100" required />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-700">Save Changes</button>
                </div>
            </form>

            <form method="POST" action="{{ route('cbt-tests.status', $test) }}" class="mt-4">
                @csrf
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-48">
                        <label class="field-label">Available From</label>
                        <input type="datetime-local" name="available_from" value="{{ optional($test->available_from)->format('Y-m-d\TH:i') }}" class="mt-1 w-full">
                    </div>
                    <div class="w-48">
                        <label class="field-label">Closing Date &amp; Time</label>
                        <input type="datetime-local" name="available_until" value="{{ optional($test->available_until)->format('Y-m-d\TH:i') }}" class="mt-1 w-full">
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" name="status" value="draft" @disabled($test->hasStudentAttempts()) class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Save as Draft</button>
                    {{-- The same one-button toggle as the teacher's page, and
                         for the same reason: this sent "locked" whatever the
                         test's state, so on a locked test it did nothing. --}}
                    @php $isLocked = $test->status === \App\Enums\CbtTestStatus::Locked; @endphp

                    <button type="submit" name="status" value="{{ $isLocked ? 'draft' : 'locked' }}" @disabled($test->hasStudentAttempts()) title="{{ $isLocked ? 'Unlock this test so it can be edited again.' : 'Lock this test so it cannot be edited.' }}" class="flex items-center gap-1.5 rounded-[8px] border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                        <i class="fa-solid {{ $isLocked ? 'fa-lock-open' : 'fa-lock' }} text-[12px]" aria-hidden="true"></i>
                        {{ $isLocked ? 'Unlock' : 'Lock' }}
                    </button>
                    <button type="submit" name="status" value="published" class="rounded-[8px] bg-green-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-green-700">Publish</button>
                    <button type="submit" name="status" value="archived" class="rounded-[8px] border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">Archive</button>
                </div>
                @if ($test->hasStudentAttempts())
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Draft/Lock are disabled — students have already started this test. You can still archive it.</p>
                @endif
            </form>
        </div>

        @if ($test->attempts->isNotEmpty())
            <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Student Attempts</h2>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($test->attempts as $attempt)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $attempt->student->fullName() }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $attempt->isSubmitted() ? 'Submitted' : 'In Progress' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $attempt->isSubmitted() ? $attempt->percentage().'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Questions</h2>
            </div>
            <div class="divide-y divide-gray-100 p-5 dark:divide-gray-700">
                @foreach ($test->questions as $question)
                    <div class="py-4 first:pt-0 last:pb-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $question->question_text }}</p>
                        <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                            @foreach ($question->options as $option)
                                <div class="flex items-center gap-2 rounded-[8px] border px-3 py-1.5 text-xs {{ $option->is_correct ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                                    <span class="font-bold">{{ $option->label }}</span>
                                    <span>{{ $option->option_text }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-dashboard-layout>
