@php
    $questionsData = $test->questions->map(fn ($q) => [
        'uuid' => $q->uuid,
        'text' => $q->question_text,
        'image' => $q->imageUrl(),
        'options' => $q->options->map(fn ($o) => ['label' => $o->label, 'text' => $o->option_text])->values()->all(),
        'correctLabel' => $q->correctOption()?->label,
    ]);
@endphp

<x-staff-layout :page-title="$test->title" :page-subtitle="$test->subject.' · '.$test->class_name">
    <div
        class="space-y-6"
        x-data="{
            mode: 'manage',
            questions: @js($questionsData),
            previewAnswers: {},
            previewSubmitted: false,
            previewSecondsLeft: {{ $test->duration_minutes * 60 }},
            previewTimer: null,
            startPreview() {
                this.mode = 'preview';
                this.previewAnswers = {};
                this.previewSubmitted = false;
                this.previewSecondsLeft = {{ $test->duration_minutes * 60 }};
                clearInterval(this.previewTimer);
                this.previewTimer = setInterval(() => {
                    if (this.previewSubmitted) return;
                    if (this.previewSecondsLeft > 0) {
                        this.previewSecondsLeft--;
                    } else {
                        this.submitPreview();
                    }
                }, 1000);
            },
            stopPreview() {
                this.mode = 'manage';
                clearInterval(this.previewTimer);
            },
            submitPreview() {
                this.previewSubmitted = true;
                clearInterval(this.previewTimer);
            },
            get previewScore() {
                return this.questions.filter(q => this.previewAnswers[q.uuid] === q.correctLabel).length;
            },
            get previewPercent() {
                return this.questions.length ? Math.round((this.previewScore / this.questions.length) * 100) : 0;
            },
            get previewMinutes() { return Math.floor(this.previewSecondsLeft / 60); },
            get previewSecondsPart() { return String(this.previewSecondsLeft % 60).padStart(2, '0'); },
        }"
    >
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('staff.cbt.tests.index', $school) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to My Tests
            </a>

            <button
                type="button"
                x-show="mode === 'manage'"
                @click="startPreview()"
                :disabled="questions.length === 0"
                class="flex items-center gap-2 rounded-[8px] border border-primary-300 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-400"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4.5l13 7.5-13 7.5v-15z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /></svg>
                Preview as Student
            </button>
            <button
                type="button"
                x-show="mode === 'preview'"
                style="display: none;"
                @click="stopPreview()"
                class="flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Exit Preview
            </button>
        </div>

        {{-- Manage mode --}}
        <div x-show="mode === 'manage'" class="space-y-6">
            {{-- Test meta + status controls --}}
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $test->title }}</h2>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $test->status === \App\Enums\CbtTestStatus::Draft,
                                'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $test->status === \App\Enums\CbtTestStatus::Locked,
                                'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $test->status === \App\Enums\CbtTestStatus::Published,
                                'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $test->status === \App\Enums\CbtTestStatus::Archived,
                            ])>{{ $test->status->label() }}</span>
                        </div>
                        <p class="field-hint mt-1">
                            {{ $test->subject }} &middot; {{ $test->class_name }} &middot; {{ $test->duration_minutes }} min &middot; Pass mark {{ $test->pass_mark }}%
                            @if ($test->questions->isNotEmpty()) &middot; {{ $test->questions->count() }} question(s) @endif
                        </p>
                        @if ($test->status === \App\Enums\CbtTestStatus::Draft || $test->status === \App\Enums\CbtTestStatus::Locked)
                            <p class="mt-1 text-xs font-semibold text-gray-400 dark:text-gray-500">Not visible to students while {{ strtolower($test->status->label()) }}.</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <form method="POST" action="{{ route('staff.cbt.tests.duplicate', [$school, $test]) }}">
                            @csrf
                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Duplicate</button>
                        </form>
                        <form method="POST" action="{{ route('staff.cbt.tests.destroy', [$school, $test]) }}" onsubmit="return confirm('Delete this test? This cannot be undone.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('staff.cbt.tests.status', [$school, $test]) }}" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
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
                        <button type="submit" name="status" value="draft" @disabled($test->hasStudentAttempts()) title="{{ $test->hasStudentAttempts() ? 'Students have already started this test.' : '' }}" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Save as Draft</button>
                    {{-- One button, both ways.

                         It only ever sent "locked", so a test that was locked
                         had a Lock button that did nothing - the way back was
                         "Save as Draft", which does not read as the opposite of
                         Lock and nobody found it.

                         Unlocking returns the test to DRAFT because that is
                         genuinely what Lock froze: students only ever see
                         Published and Archived tests, so a locked test is a
                         finished draft held back from editing, not a live one
                         suspended. Returning it to Draft is the exact inverse.
                         Publishing is a separate, deliberate act, and it stays
                         that way. --}}
                    @php $isLocked = $test->status === \App\Enums\CbtTestStatus::Locked; @endphp

                        <button type="submit" name="status" value="{{ $isLocked ? 'draft' : 'locked' }}" @disabled($test->hasStudentAttempts()) title="{{ $test->hasStudentAttempts() ? 'Students have already started this test.' : ($isLocked ? 'Unlock this test so it can be edited again.' : 'Lock this test so it cannot be edited.') }}" class="flex items-center gap-1.5 rounded-[8px] border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                            <i class="fa-solid {{ $isLocked ? 'fa-lock-open' : 'fa-lock' }} text-[12px]" aria-hidden="true"></i>
                            {{ $isLocked ? 'Unlock' : 'Lock' }}
                        </button>
                        <button type="submit" name="status" value="published" @disabled($publishBlocker) title="{{ $publishBlocker ?? '' }}" class="rounded-[8px] bg-green-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0">Publish</button>
                        <button type="submit" name="status" value="archived" class="rounded-[8px] border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">Archive</button>
                    </div>
                </form>
                @if ($publishBlocker)
                    {{-- Publishing is the moment a test starts producing marks on
                         students' records, so the reason it is being held back is
                         worth stating in full rather than leaving the button dead. --}}
                    <div class="mt-3 flex items-start gap-2.5 rounded-[8px] border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                        <p class="text-xs leading-[1.6] text-amber-900 dark:text-amber-300">{{ $publishBlocker }}</p>
                    </div>
                @endif
                @if ($test->hasStudentAttempts())
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Draft/Lock are disabled — students have already started this test. You can still archive it.</p>
                @endif
            </div>

            {{-- Document upload panel --}}
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Upload a Document to Auto-Extract Questions</h3>
                <p class="field-hint mt-1">
                    Upload a PDF or Word document of your questions, up to
                    {{ \App\Http\Controllers\Staff\Cbt\DocumentUploadController::maxUploadLabel() }}, and the system will read it
                    and add the questions below automatically. Review each extracted question before publishing.
                </p>

                @if ($extractionWarning)
                    {{-- Said before the upload, not after it. Waiting on a document
                         that cannot be read is the failure this whole change is
                         about. --}}
                    <div class="mb-4 flex items-start gap-3 rounded-[8px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                        <p class="text-xs leading-[1.6] text-amber-900 dark:text-amber-300">{{ $extractionWarning }}</p>
                    </div>
                @endif

                <x-upload-progress-form
                    id="document-upload"
                    :action="route('staff.cbt.tests.uploads.store', [$school, $test])"
                    :max-mb="21"
                    class="mt-3 space-y-2"
                >
                    <div>
                        <x-input-label for="cbt-test-upload-file" value="Document" />
                        <input id="cbt-test-upload-file" type="file" name="file" accept=".pdf,.doc,.docx" required class="mt-1 w-full">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                </x-upload-progress-form>

                @if ($test->documentUploads->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($test->documentUploads as $upload)
                            <a href="{{ route('staff.cbt.tests.uploads.show', [$school, $test, $upload]) }}" class="flex items-center justify-between gap-2 rounded-[8px] border border-gray-200 px-3 py-2 text-sm transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                                <span class="truncate text-gray-700 dark:text-gray-200">{{ $upload->original_filename }}</span>
                                <span @class([
                                    'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $upload->status === \App\Enums\CbtDocumentUploadStatus::Pending,
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $upload->status->isInProgress() && $upload->status !== \App\Enums\CbtDocumentUploadStatus::Pending,
                                    'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $upload->status === \App\Enums\CbtDocumentUploadStatus::Completed,
                                    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $upload->status === \App\Enums\CbtDocumentUploadStatus::Failed,
                                ])>{{ $upload->status->label() }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Question list --}}
            <div x-data="{
                open: false,
                editing: null,
                options: ['', '', '', ''],
                correctIndex: 0,
                openAdd() { this.editing = null; this.options = ['', '', '', '']; this.correctIndex = 0; this.open = true; },
                openEdit(q) {
                    this.editing = q;
                    this.options = q.options.map(o => o.text);
                    this.correctIndex = Math.max(0, q.options.findIndex(o => o.label === q.correctLabel));
                    this.open = true;
                },
                addOption() { if (this.options.length < 5) this.options.push(''); },
                removeOption(i) {
                    if (this.options.length <= 2) return;
                    this.options.splice(i, 1);
                    if (this.correctIndex >= this.options.length) this.correctIndex = this.options.length - 1;
                },
            }">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Questions</h3>
                    <button type="button" @click="openAdd()" class="flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                        Add Question
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    @forelse ($test->questions as $question)
                        @php $correctLabel = $question->correctOption()?->label; @endphp
                        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $loop->iteration }}. {{ $question->question_text }}
                                    @if ($question->needs_review)
                                        <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Needs Review</span>
                                    @endif
                                </p>
                                <div class="flex shrink-0 items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEdit(@js([
                                            'uuid' => $question->uuid,
                                            'text' => $question->question_text,
                                            'options' => $question->options->map(fn ($o) => ['label' => $o->label, 'text' => $o->option_text])->values()->all(),
                                            'correctLabel' => $correctLabel,
                                        ]))"
                                        class="rounded-[8px] border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('staff.cbt.tests.questions.destroy', [$school, $test, $question]) }}" onsubmit="return confirm('Delete this question?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-[8px] border border-red-300 px-2.5 py-1 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                    </form>
                                </div>
                            </div>

                            @if ($question->review_notes)
                                <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">{{ $question->review_notes }}</p>
                            @endif

                            @if ($question->imageUrl())
                                <img src="{{ $question->imageUrl() }}" class="mt-3 max-h-56 rounded-[8px] border border-gray-200 object-contain dark:border-gray-700">
                            @endif

                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($question->options as $option)
                                    <div class="flex items-center gap-2 rounded-[8px] border px-3 py-2 text-sm {{ $option->is_correct ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                                        <span class="font-bold">{{ $option->label }}</span>
                                        <span>{{ $option->option_text }}</span>
                                        @if ($option->is_correct)
                                            <svg class="ml-auto h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[10px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            No questions yet. Add one manually or upload a document above.
                        </div>
                    @endforelse
                </div>

                {{-- Add/Edit Question modal --}}
                <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                    <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Question' : 'Add Question'"></h3>
                        <form
                            method="POST"
                            :action="editing
                                ? '{{ route('staff.cbt.tests.questions.update', [$school, $test, '__ID__']) }}'.replace('__ID__', editing.uuid)
                                : '{{ route('staff.cbt.tests.questions.store', [$school, $test]) }}'"
                            enctype="multipart/form-data"
                            class="mt-4 space-y-2"
                        >
                            @csrf
                            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                            <x-textarea-field
                                name="question_text"
                                label="Question"
                                icon="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"
                                rows="3"
                                required
                                x-text="editing ? editing.text : ''"
                            />

                            <div>
                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="w-full">
                                <p class="field-hint mt-1">Optional image/diagram. Leave blank to keep the existing one when editing.</p>
                            </div>

                            <div>
                                <p class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Options</p>
                                <div class="space-y-2">
                                    <template x-for="(option, i) in options" :key="i">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" name="correct_index" :value="i" x-model.number="correctIndex" class="shrink-0 text-primary-500">
                                            <input
                                                type="text"
                                                :name="'options[' + i + ']'"
                                                x-model="options[i]"
                                                required
                                                :placeholder="'Option ' + String.fromCharCode(65 + i)"
                                                class="flex-1"
                                            >
                                            <button type="button" @click="removeOption(i)" x-show="options.length > 2" class="shrink-0 rounded-[8px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="addOption()" x-show="options.length < 5" class="mt-2 text-xs font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">+ Add another option</button>
                                <p class="field-hint mt-2">Select the radio button next to the correct answer.</p>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                                <button type="submit" class="rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Save Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview mode --}}
        <div x-show="mode === 'preview'" style="display: none;">
            <div class="flex items-center justify-between rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $test->title }} &mdash; Student Preview</p>
                    <p class="field-hint">This is exactly what students will see. Nothing here is saved.</p>
                </div>
                <div class="rounded-[8px] bg-gray-900 px-3 py-1.5 font-mono text-sm font-bold text-white dark:bg-black" x-show="!previewSubmitted">
                    <span x-text="previewMinutes"></span>:<span x-text="previewSecondsPart"></span>
                </div>
            </div>

            <template x-if="!previewSubmitted">
                <div class="mt-4 space-y-4">
                    <template x-for="(question, index) in questions" :key="'preview-' + question.uuid">
                        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                <span x-text="index + 1"></span>. <span x-text="question.text"></span>
                            </p>
                            <img x-show="question.image" :src="question.image" class="mt-3 max-h-56 rounded-[8px] border border-gray-200 object-contain dark:border-gray-700" style="display: none;">
                            <div class="mt-3 space-y-2">
                                <template x-for="option in question.options" :key="option.label">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 text-sm transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                        <input type="radio" :name="'preview-' + question.uuid" :value="option.label" x-model="previewAnswers[question.uuid]" class="text-primary-500">
                                        <span class="font-bold" x-text="option.label"></span>
                                        <span x-text="option.text"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="submitPreview()" class="rounded-[8px] bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-lg">
                        Submit
                    </button>
                </div>
            </template>

            <template x-if="previewSubmitted">
                <div class="mt-4 rounded-[10px] border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Preview Score</p>
                    <p class="mt-2 text-4xl font-extrabold text-gray-900 dark:text-white"><span x-text="previewScore"></span> / <span x-text="questions.length"></span></p>
                    <p class="mt-1 text-sm" :class="previewPercent >= {{ $test->pass_mark }} ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        <span x-text="previewPercent"></span>% &mdash; <span x-text="previewPercent >= {{ $test->pass_mark }} ? 'Pass' : 'Fail'"></span> (pass mark {{ $test->pass_mark }}%)
                    </p>
                    <button type="button" @click="startPreview()" class="mt-4 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Retake Preview</button>
                </div>
            </template>
        </div>
    </div>
</x-staff-layout>
