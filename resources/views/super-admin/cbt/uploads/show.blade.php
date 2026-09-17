@php
    $questionsByExam = $upload->questions->groupBy('cbt_exam_id');
    $publishedCount = $upload->questions->where('is_published', true)->count();
    $reviewCount = $upload->questions->where('needs_review', true)->count();
    $readyCount = $upload->questions->where('needs_review', false)->where('is_published', false)->count();
@endphp

<x-super-admin-layout :page-title="$upload->original_filename" page-subtitle="Check what was read from this document, then publish it for students.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @error('upload')
            <div class="rounded-[5px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                {{ $message }}
            </div>
        @enderror

        <a href="{{ route('super-admin.cbt.uploads.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <i class="fa-solid fa-chevron-left text-xs"></i>
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
                <p class="text-sm font-bold text-gray-900 dark:text-white">Confirm Exam Body &amp; Subject</p>
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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400"><i class="fa-solid fa-book mr-1.5"></i>Exam Body / Subject</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $upload->examBody?->name }} &middot; {{ $upload->subject?->name }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-purple-600 dark:text-purple-400"><i class="fa-solid fa-calendar-days mr-1.5"></i>Years Detected</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ collect($upload->detected_years)->sort()->implode(', ') ?: '—' }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-amber-600 dark:text-amber-400"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Questions Needing Review</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $upload->questions_needing_review_count }} / {{ $upload->questions_extracted_count }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-sky-600 dark:text-sky-400"><i class="fa-solid fa-copy mr-1.5"></i>Duplicates Skipped</p>
                    <p class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ $upload->duplicates_skipped ?? 0 }}</p>
                </div>
            </div>

            @if (! empty($upload->warnings))
                <div class="rounded-[5px] border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-900/20 lg:rounded-[10px]">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-300"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>Check These Before Publishing</p>
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

            {{-- Publishing is the only thing that puts these questions in front of
                 a student. Until then they sit here as a draft, however many
                 times the document is read again. --}}
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            @if ($publishedCount > 0)
                                <span class="mr-1.5 inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400"><i class="fa-solid fa-circle-check"></i>Published</span>
                            @else
                                <span class="mr-1.5 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"><i class="fa-solid fa-pen-ruler"></i>Draft</span>
                            @endif
                            {{ $publishedCount }} of {{ $upload->questions->count() }} question(s) are available to students
                        </p>
                        <p class="field-hint mt-1">
                            @if ($reviewCount > 0)
                                {{ $reviewCount }} question(s) still need review and stay hidden until they are corrected in the question bank. Publishing releases the {{ $readyCount }} that are ready.
                            @elseif ($publishedCount > 0)
                                Students can practise every question from this document. Unpublish to hide them again.
                            @else
                                Nothing from this document is visible to students yet. Read through the questions below, then publish.
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if ($readyCount > 0)
                            <form method="POST" action="{{ route('super-admin.cbt.uploads.publish', $upload) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                                    <i class="fa-solid fa-upload"></i>
                                    {{ $publishedCount > 0 ? 'Publish the Rest' : 'Publish to Students' }}
                                </button>
                            </form>
                        @endif
                        @if ($publishedCount > 0)
                            <form method="POST" action="{{ route('super-admin.cbt.uploads.unpublish', $upload) }}" onsubmit="return confirm('Hide these questions from students again?');">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                    <i class="fa-solid fa-eye-slash"></i>
                                    Unpublish
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- The way out of a document read badly, including one read before
                 the reader was fixed: read it again from the stored file. --}}
            <div class="flex flex-col gap-3 rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Questions look wrong?</p>
                    <p class="field-hint mt-0.5">
                        @if ($publishedCount > 0)
                            Unpublish first, then read this document again. Its questions are replaced, split by year, and never added twice.
                        @else
                            Read this document again from the stored file. Its questions are replaced, split by year, and never added twice.
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('super-admin.cbt.uploads.retry', $upload) }}" onsubmit="return confirm('Read this document again? The questions it produced are replaced.');" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        @disabled($publishedCount > 0)
                        class="inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <i class="fa-solid fa-rotate-right"></i>
                        Read This Document Again
                    </button>
                </form>
            </div>

            @if (! empty($upload->extracted_images))
                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-images mr-1.5"></i>Images Found in the Document</h2>
                    <p class="field-hint mt-1">Pictures printed beside a question are already attached to it below. Any left over can be attached by hand via "Edit" in the question bank.</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach ($upload->extractedImageUrls() as $imageUrl)
                            <img src="{{ $imageUrl }}" alt="" class="h-24 w-24 rounded-[5px] border border-gray-200 object-cover dark:border-gray-700">
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                @foreach ($questionsByExam as $examId => $examQuestions)
                    @php $exam = $examQuestions->first()->exam; @endphp
                    <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                            <div>
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $exam->title() }}</h2>
                                <p class="field-hint mt-0.5">{{ $examQuestions->count() }} question(s) &middot; {{ $examQuestions->where('is_published', true)->count() }} published</p>
                            </div>
                            <a href="{{ route('super-admin.cbt.exams.show', $exam) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Full Question Bank</a>
                        </div>
                        <div class="divide-y divide-gray-100 p-6 dark:divide-gray-700">
                            @php $lastPassage = null; @endphp
                            @foreach ($examQuestions as $question)
                                {{-- A passage is printed once, above the run of
                                     questions that share it, the way the paper
                                     printed it. --}}
                                @if (filled($question->passage) && $question->passage !== $lastPassage)
                                    <div class="py-4 first:pt-0">
                                        <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400"><i class="fa-solid fa-align-left mr-1.5"></i>Passage</p>
                                        <div class="mt-2 max-h-64 overflow-y-auto rounded-[5px] border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300 lg:rounded-[8px]">{{ $question->passage }}</div>
                                    </div>
                                @endif
                                @php $lastPassage = $question->passage; @endphp

                                <div class="py-4 first:pt-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white whitespace-pre-line">{{ $question->question_number ?? $loop->iteration }}. {{ $question->question_text }}</p>
                                        <div class="flex shrink-0 flex-wrap justify-end gap-1.5">
                                            @if ($question->needs_review)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Needs review</span>
                                            @endif
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $question->is_published ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                                {{ $question->is_published ? 'Published' : 'Draft' }}
                                            </span>
                                        </div>
                                    </div>
                                    @if ($question->review_notes)
                                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $question->review_notes }}</p>
                                    @endif
                                    @if ($question->imageUrl())
                                        <img src="{{ $question->imageUrl() }}" alt="" class="mt-3 max-h-56 rounded-[5px] border border-gray-200 dark:border-gray-700">
                                    @endif
                                    <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                        @foreach ($question->options as $option)
                                            <div class="flex items-center gap-2 rounded-[5px] border px-3 py-1.5 text-xs lg:rounded-[8px] {{ $option->is_correct ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                                                <span class="font-bold">{{ $option->label }}</span>
                                                <span>{{ $option->option_text }}</span>
                                                @if ($option->is_correct)
                                                    <i class="fa-solid fa-check ml-auto"></i>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @if (filled($question->explanation))
                                        <p class="mt-2 rounded-[5px] bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-900/20 dark:text-sky-300 lg:rounded-[8px]">
                                            <span class="font-semibold">Explanation:</span> {{ $question->explanation }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-super-admin-layout>
