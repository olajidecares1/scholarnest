<x-staff-layout :page-title="$upload->original_filename" :page-subtitle="'Review extraction results for '.$test->title">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('staff.cbt.tests.show', [$school, $test]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to {{ $test->title }}
        </a>

        <x-upload-status-panel
            :upload="$upload"
            :stalled="$stalled"
            :status-url="route('staff.cbt.tests.uploads.status', [$school, $test, $upload])"
            :retry-url="route('staff.cbt.tests.uploads.retry', [$school, $test, $upload])"
        />

        @if ($upload->status->value === 'completed')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Questions Extracted</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upload->questions_extracted_count }}</p>
                </div>
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Needing Review</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upload->questions_needing_review_count }}</p>
                </div>
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-medium text-sky-600 dark:text-sky-400">Already in This Test</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $upload->duplicates_skipped ?? 0 }}</p>
                </div>
            </div>

            @if (! empty($upload->warnings))
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-900/20">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-300"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>Check These Before Using the Test</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($upload->warnings as $warning)
                            <li class="flex gap-2 text-xs text-amber-800 dark:text-amber-300">
                                <i class="fa-solid fa-angle-right mt-0.5 shrink-0"></i>
                                <span>{{ $warning }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($yearTests->count() > 1)
                {{-- A compilation of several years is several examinations, so
                     each year became its own test rather than one test of
                     hundreds of questions. --}}
                <div class="rounded-[10px] border border-primary-200 bg-primary-50 p-6 dark:border-primary-800 dark:bg-primary-900/20">
                    <p class="text-sm font-bold text-primary-900 dark:text-primary-200"><i class="fa-solid fa-layer-group mr-1.5"></i>One Test for Each Year</p>
                    <p class="mt-1 text-xs leading-relaxed text-primary-900/80 dark:text-primary-200/80">
                        This document holds {{ $yearTests->count() }} years of questions, so each year is its own test, the way it was sat.
                        The new ones are drafts: open each to check it, then publish it for your class.
                    </p>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($yearTests as $yearTest)
                            <a href="{{ route('staff.cbt.tests.show', [$school, $yearTest]) }}" class="flex items-center justify-between gap-2 rounded-[8px] border border-primary-200 bg-white px-3 py-2 text-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm dark:border-primary-800 dark:bg-gray-800">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900 dark:text-white">{{ $yearTest->source_year }}</span>
                                    <small class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $yearTest->title }}</small>
                                </span>
                                <span class="shrink-0 text-xs font-semibold text-gray-600 dark:text-gray-300">{{ $yearTest->questions_count }} questions &middot; {{ $yearTest->status->label() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-3 rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Questions look wrong?</p>
                    <small class="field-hint mt-0.5">Read this document again. Its questions are replaced, never added twice, and you do not need to upload the file again.</small>
                    @error('upload')
                        <p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <form method="POST" action="{{ route('staff.cbt.tests.uploads.retry', [$school, $test, $upload]) }}" onsubmit="return confirm('Read this document again? The questions it produced are replaced.');" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-rotate-right"></i>
                        Read This Document Again
                    </button>
                </form>
            </div>

            @if (! empty($upload->extracted_images))
                <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Images Found in the Document</h2>
                    <small class="field-hint mt-1">Attach any of these to the matching question via "Edit" on the test page &mdash; automatic diagram-to-question matching isn't reliable enough to do silently.</small>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($upload->extractedImageUrls() as $imageUrl)
                            <img src="{{ $imageUrl }}" class="h-24 w-24 rounded-[8px] border border-gray-200 object-cover dark:border-gray-700">
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Extracted Questions</h2>
                    <a href="{{ route('staff.cbt.tests.show', [$school, $test]) }}" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage on Test Page</a>
                </div>
                <div class="divide-y divide-gray-100 p-6 dark:divide-gray-700">
                    @php
                        $lastPassage = null;
                        $lastTest = null;
                    @endphp
                    @foreach ($upload->questions as $question)
                        @if ($yearTests->count() > 1 && $question->cbt_test_id !== $lastTest)
                            <p class="pb-2 pt-6 text-sm font-bold text-gray-900 first:pt-0 dark:text-white">
                                <i class="fa-solid fa-calendar-days mr-1.5 text-primary-600"></i>{{ $question->test->title }}
                            </p>
                            @php $lastPassage = null; @endphp
                        @endif
                        @php $lastTest = $question->cbt_test_id; @endphp

                        @if (filled($question->passage) && $question->passage !== $lastPassage)
                            <div class="py-4 first:pt-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400"><i class="fa-solid fa-align-left mr-1.5"></i>Passage</p>
                                <div class="mt-2 max-h-64 overflow-y-auto whitespace-pre-line rounded-[8px] border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">{{ $question->passage }}</div>
                            </div>
                        @endif
                        @php $lastPassage = $question->passage; @endphp

                        <div class="py-4 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <p class="whitespace-pre-line text-sm font-semibold text-gray-900 dark:text-white">{{ $question->question_number ?? $loop->iteration }}. {{ $question->question_text }}</p>
                                @if ($question->needs_review)
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Needs review</span>
                                @endif
                            </div>
                            @if ($question->review_notes)
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $question->review_notes }}</p>
                            @endif
                            @if ($question->imageUrl())
                                <img src="{{ $question->imageUrl() }}" alt="" class="mt-3 max-h-56 rounded-[8px] border border-gray-200 dark:border-gray-700">
                            @endif
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
        @endif
    </div>
</x-staff-layout>
