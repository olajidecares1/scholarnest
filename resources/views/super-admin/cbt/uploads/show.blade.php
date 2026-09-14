@php
    $questionsByExam = $upload->questions->groupBy('cbt_exam_id');
@endphp

<x-super-admin-layout :page-title="$upload->original_filename" page-subtitle="Review what was extracted from this document.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('super-admin.cbt.uploads.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Uploads
        </a>

        {{-- The AkademicNest Team CAN start a worker, so they are the audience that
             gets told which command does it. --}}
        <x-upload-status-panel
            :upload="$upload"
            :stalled="$stalled"
            :can-operate-the-server="true"
            :status-url="route('super-admin.cbt.uploads.status', $upload)"
            :retry-url="route('super-admin.cbt.uploads.retry', $upload)"
        />

        @if ($upload->status->value === 'needs_mapping')
            <div class="rounded-[5px] border border-amber-200 bg-white p-6 shadow-sm dark:border-amber-800 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-bold text-gray-900 dark:text-white">Confirm Exam Body & Subject</p>
                <p class="field-hint mt-1">
                    The document names
                    <strong>{{ $upload->ai_response['exam_body'] ?? 'an exam body' }}</strong> /
                    <strong>{{ $upload->ai_response['subject'] ?? 'a subject' }}</strong>,
                    but no matching record exists yet. Pick the correct ones below (or add them first from the CBT overview page) to import the extracted questions.
                </p>

                <form method="POST" action="{{ route('super-admin.cbt.uploads.mapping', $upload) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <x-select-field
                        name="cbt_exam_body_id"
                        label="Exam Body"
                        required
                        :options="$examBodies->pluck('name', 'id')->all()"
                    />
                    <x-select-field
                        name="cbt_subject_id"
                        label="Subject"
                        required
                        :options="$subjects->pluck('name', 'id')->all()"
                    />
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">Import Questions</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($upload->status->value === 'completed')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Exam Body / Subject</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $upload->examBody?->name }} &middot; {{ $upload->subject?->name }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Years Detected</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ collect($upload->detected_years)->sort()->implode(', ') }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Questions Needing Review</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $upload->questions_needing_review_count }} / {{ $upload->questions_extracted_count }}</p>
                </div>
            </div>

            @if (! empty($upload->extracted_images))
                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Images Found in the Document</h2>
                    <p class="field-hint mt-1">Attach any of these to the matching question below via "Edit" in the question bank &mdash; automatic diagram-to-question matching isn't reliable enough to do silently.</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($upload->extractedImageUrls() as $imageUrl)
                            <img src="{{ $imageUrl }}" class="h-24 w-24 rounded-[5px] border border-gray-200 object-cover dark:border-gray-700">
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                @foreach ($questionsByExam as $examId => $examQuestions)
                    @php $exam = $examQuestions->first()->exam; @endphp
                    <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                            <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $exam->title() }}</h2>
                            <a href="{{ route('super-admin.cbt.exams.show', $exam) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Full Question Bank</a>
                        </div>
                        <div class="divide-y divide-gray-100 p-6 dark:divide-gray-700">
                            @foreach ($examQuestions as $question)
                                <div class="py-4 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $question->question_text }}</p>
                                        @if ($question->needs_review)
                                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Needs review</span>
                                        @endif
                                    </div>
                                    @if ($question->review_notes)
                                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $question->review_notes }}</p>
                                    @endif
                                    <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                        @foreach ($question->options as $option)
                                            <div class="flex items-center gap-2 rounded-[5px] border px-3 py-1.5 text-xs lg:rounded-[8px] {{ $option->is_correct ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                                                <span class="font-bold">{{ $option->label }}</span>
                                                <span>{{ $option->option_text }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-super-admin-layout>
