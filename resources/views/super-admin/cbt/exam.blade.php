@php
    $questionsData = $exam->questions->map(fn ($q) => [
        'uuid' => $q->uuid,
        'text' => $q->question_text,
        'image' => $q->imageUrl(),
        'options' => $q->options->map(fn ($o) => ['label' => $o->label, 'text' => $o->option_text])->values()->all(),
        'correctLabel' => $q->correctOption()?->label,
    ]);
@endphp

<x-super-admin-layout :page-title="$exam->title()" page-subtitle="Manage the question bank, or preview the exam as a candidate would see it.">
    <div
        class="space-y-6"
        x-data="{
            mode: 'manage',
            questions: @js($questionsData),
            previewAnswers: {},
            previewSubmitted: false,
            previewSecondsLeft: {{ $exam->duration_minutes * 60 }},
            previewTimer: null,
            startPreview() {
                this.mode = 'preview';
                this.previewAnswers = {};
                this.previewSubmitted = false;
                this.previewSecondsLeft = {{ $exam->duration_minutes * 60 }};
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
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('super-admin.cbt.exam-bodies.show', $exam->examBody) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to {{ $exam->examBody->name }}
            </a>

            <button
                type="button"
                x-show="mode === 'manage'"
                @click="startPreview()"
                :disabled="questions.length === 0"
                class="flex items-center gap-2 rounded-[5px] border border-primary-300 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-400 lg:rounded-[10px]"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 4.5l13 7.5-13 7.5v-15z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" /></svg>
                Preview Exam
            </button>

            <button
                type="button"
                x-show="mode === 'preview'"
                style="display: none;"
                @click="stopPreview()"
                class="flex items-center gap-2 rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[10px]"
            >
                Exit Preview
            </button>
        </div>

        {{-- Manage mode --}}
        <div x-show="mode === 'manage'" x-data="{
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
            <div class="flex justify-end">
                <button type="button" @click="openAdd()" class="flex items-center gap-2 rounded-[5px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md lg:rounded-[10px]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Question
                </button>
            </div>

            <div class="mt-4 space-y-4">
                @forelse ($exam->questions as $question)
                    @php $correctLabel = $question->correctOption()?->label; @endphp
                    <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $loop->iteration }}. {{ $question->question_text }}
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
                                    class="rounded-[5px] border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 lg:rounded-[6px]"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('super-admin.cbt.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-[5px] border border-red-300 px-2.5 py-1 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 lg:rounded-[6px]">Delete</button>
                                </form>
                            </div>
                        </div>

                        @if ($question->imageUrl())
                            <img src="{{ $question->imageUrl() }}" class="mt-3 max-h-56 rounded-[5px] border border-gray-200 object-contain dark:border-gray-700">
                        @endif

                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($question->options as $option)
                                <div class="flex items-center gap-2 rounded-[5px] border px-3 py-2 text-sm lg:rounded-[8px] {{ $option->is_correct ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
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
                    <div class="rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 lg:rounded-[10px]">
                        No questions yet. Click "Add Question" to build the question bank for this exam.
                    </div>
                @endforelse
            </div>

            {{-- Add/Edit Question modal --}}
            <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
                <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[5px] bg-white p-6 dark:bg-gray-800 lg:rounded-[10px]">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Question' : 'Add Question'"></h3>
                    <form
                        method="POST"
                        :action="editing
                            ? '{{ route('super-admin.cbt.questions.update', ['question' => '__ID__']) }}'.replace('__ID__', editing.uuid)
                            : '{{ route('super-admin.cbt.questions.store', $exam) }}'"
                        enctype="multipart/form-data"
                        class="mt-4 space-y-4"
                    >
                        @csrf
                        <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                        <x-textarea-field
                            name="question_text"
                            label="Question"
                            icon="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01"
                            helper="The question exactly as it should appear to candidates."
                            rows="3"
                            required
                            x-text="editing ? editing.text : ''"
                        />

                        <div>
                            <x-input-label for="cbt-question-image" value="Image (optional)" />
                            <input
                                id="cbt-question-image"
                                name="image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="mt-1 w-full rounded-[5px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[5px] file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 lg:rounded-[10px]"
                            >
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">For diagrams, charts, or images referenced in the question. Leave blank to keep the existing image when editing.</p>
                        </div>

                        <div>
                            <p class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Options</p>
                            <div class="space-y-2">
                                <template x-for="(option, i) in options" :key="i">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="correct_index" :value="i" x-model.number="correctIndex" class="shrink-0 text-primary-500 focus:ring-primary-500">
                                        <input
                                            type="text"
                                            :name="'options[' + i + ']'"
                                            x-model="options[i]"
                                            required
                                            :placeholder="'Option ' + String.fromCharCode(65 + i)"
                                            class="flex-1 rounded-[8px] border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-500/10 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        >
                                        <button type="button" @click="removeOption(i)" x-show="options.length > 2" class="shrink-0 rounded-[5px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addOption()" x-show="options.length < 5" class="mt-2 text-xs font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">+ Add another option</button>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Select the radio button next to the correct answer.</p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200 lg:rounded-[10px]">Cancel</button>
                            <button type="submit" class="rounded-[5px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 lg:rounded-[10px]">Save Question</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Preview mode --}}
        <div x-show="mode === 'preview'" style="display: none;">
            <div class="flex items-center justify-between rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $exam->title() }} &mdash; Preview</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">This is a QA preview for admins. Nothing is saved.</p>
                </div>
                <div class="rounded-[5px] bg-gray-900 px-3 py-1.5 font-mono text-sm font-bold text-white dark:bg-black lg:rounded-[8px]" x-show="!previewSubmitted">
                    <span x-text="previewMinutes"></span>:<span x-text="previewSecondsPart"></span>
                </div>
            </div>

            <template x-if="!previewSubmitted">
                <div class="mt-4 space-y-4">
                    <template x-for="(question, index) in questions" :key="'preview-' + question.uuid">
                        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                <span x-text="index + 1"></span>. <span x-text="question.text"></span>
                            </p>
                            <img x-show="question.image" :src="question.image" class="mt-3 max-h-56 rounded-[5px] border border-gray-200 object-contain dark:border-gray-700" style="display: none;">
                            <div class="mt-3 space-y-2">
                                <template x-for="option in question.options" :key="option.label">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-[5px] border border-gray-200 px-3 py-2 text-sm transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700/50 lg:rounded-[8px]">
                                        <input type="radio" :name="'preview-' + question.uuid" :value="option.label" x-model="previewAnswers[question.uuid]" class="text-primary-500 focus:ring-primary-500">
                                        <span class="font-bold" x-text="option.label"></span>
                                        <span x-text="option.text"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="submitPreview()" class="rounded-[5px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg lg:rounded-[10px]">
                        Submit
                    </button>
                </div>
            </template>

            <template x-if="previewSubmitted">
                <div class="mt-4 rounded-[5px] border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Preview Score</p>
                    <p class="mt-2 text-4xl font-extrabold text-gray-900 dark:text-white"><span x-text="previewScore"></span> / <span x-text="questions.length"></span></p>
                    <p class="mt-1 text-sm" :class="previewPercent >= {{ $exam->pass_mark }} ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        <span x-text="previewPercent"></span>% &mdash; <span x-text="previewPercent >= {{ $exam->pass_mark }} ? 'Pass' : 'Fail'"></span> (pass mark {{ $exam->pass_mark }}%)
                    </p>
                    <button type="button" @click="startPreview()" class="mt-4 rounded-[5px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200 lg:rounded-[10px]">Retake Preview</button>
                </div>
            </template>
        </div>
    </div>
</x-super-admin-layout>
