<x-staff-layout page-title="CBT Management" page-subtitle="Create tests by hand, or upload a Word or PDF question paper and let AkademicNest turn it into a CBT.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-gray-500 dark:text-gray-400">Tests you create here are delivered to students in the target class as "My Tests". Create a test first, then upload a <span class="font-semibold">Word (.docx)</span> or <span class="font-semibold">PDF</span> paper on the test's page - the questions, options and answer key are read out of it for you to check before you publish.</p>
            <button
                type="button"
                @click="open = true"
                class="flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Create Test
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($tests as $test)
                <a href="{{ route('staff.cbt.tests.show', [$school, $test]) }}" class="block rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-bold text-gray-900 dark:text-white">{{ $test->title }}</p>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $test->status === \App\Enums\CbtTestStatus::Draft,
                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $test->status === \App\Enums\CbtTestStatus::Locked,
                            'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $test->status === \App\Enums\CbtTestStatus::Published,
                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $test->status === \App\Enums\CbtTestStatus::Archived,
                        ])>{{ $test->status->label() }}</span>
                    </div>
                    <p class="field-hint mt-1">{{ $test->subject }} &middot; {{ $test->class_name }}</p>
                    <p class="mt-3 text-xs text-gray-400">{{ $test->questions_count }} question(s) &middot; {{ $test->duration_minutes }} min</p>
                </a>
            @empty
                <div class="col-span-full rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No tests yet. Click "Create Test" to get started - then upload a Word or PDF question paper and AkademicNest will extract the questions for you.</p>
                </div>
            @endforelse
        </div>

        {{-- Create Test modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Create Test</h3>
                <form method="POST" action="{{ route('staff.cbt.tests.store', $school) }}" class="mt-4 space-y-2">
                    @csrf
                    <x-text-field name="title" label="Title" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" placeholder="e.g. First Term Mathematics Test" required />
                    <x-text-field name="subject" label="Subject" icon="M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z" placeholder="e.g. Mathematics" required />
                    <x-text-field name="class_name" label="Class" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5" placeholder="e.g. JSS 1" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-text-field name="duration_minutes" type="number" label="Duration (minutes)" value="30" min="5" max="300" required />
                        <x-text-field name="pass_mark" type="number" label="Pass Mark (%)" value="50" min="1" max="100" required />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-staff-layout>
