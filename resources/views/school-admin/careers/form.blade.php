@php
    $editing = $job->exists;
    $typeOptions = collect($employmentTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    $questionTypeOptions = collect($questionTypes)->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();

    $initialQuestions = old('questions', $job->relationLoaded('questions')
        ? $job->questions->map(fn ($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'type' => $q->type->value,
            'options' => implode(', ', (array) $q->options),
            'required' => $q->is_required,
        ])->values()->all()
        : []);

    $card = 'rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 lg:rounded-[10px]';
    $sectionTitle = 'flex items-center gap-2 text-[15px] font-bold text-gray-900 dark:text-white';
@endphp

<x-dashboard-layout :page-title="$editing ? 'Edit vacancy' : 'Create vacancy'" :page-subtitle="$editing ? $job->title : 'Advertise a position on your Job Portal.'">
    <form
        method="POST"
        action="{{ $editing ? route('careers.update', $job) : route('careers.store') }}"
        enctype="multipart/form-data"
        class="mx-auto max-w-4xl space-y-6"
        x-data="{ submitting: false }"
        x-on:submit="submitting = true"
    >
        @csrf
        @if ($editing) @method('PUT') @endif

        <a href="{{ $editing ? route('careers.show', $job) : route('careers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
            <i class="fa-solid fa-arrow-left text-[12px]" aria-hidden="true"></i>
            {{ $editing ? 'Back to vacancy' : 'Back to recruitment' }}
        </a>

        @if ($errors->any())
            <div class="rounded-[5px] border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300 lg:rounded-[10px]" role="alert">
                <p class="font-bold"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Please correct the highlighted fields.</p>
            </div>
        @endif

        {{-- The school, as it will appear, read from the school's profile. --}}
        <section class="{{ $card }}">
            <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-school text-primary-500" aria-hidden="true"></i>School details on this vacancy</h2>
            <div class="mt-4 flex items-center gap-4">
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="" class="h-14 w-14 shrink-0 rounded-[8px] border border-gray-200 object-contain dark:border-gray-700">
                @else
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[8px] bg-primary-100 text-xl font-extrabold text-primary-700">{{ Str::of($school->name)->substr(0, 1)->upper() }}</span>
                @endif
                <div class="min-w-0 text-[13px]">
                    <p class="font-bold text-gray-900 dark:text-white">{{ $school->name }}</p>
                    <p class="text-gray-500 dark:text-gray-400">{{ $contact->address ?: 'No address set' }}</p>
                    <p class="text-gray-500 dark:text-gray-400">{{ collect([$contact->phone, $contact->email])->filter()->implode(' · ') ?: 'No contact details set' }}</p>
                </div>
            </div>
            <p class="mt-3 text-[12.5px] text-gray-500 dark:text-gray-400">
                These come from your school settings automatically.
                <a href="{{ route('settings.index') }}" class="font-semibold text-primary-700 hover:underline">Update school settings</a>
            </p>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-briefcase text-primary-500" aria-hidden="true"></i>The position</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-text-field name="title" label="Job / position title" icon="fa-id-badge" :value="$job->title" placeholder="e.g. Mathematics Teacher" required />
                </div>
                <x-text-field name="department" label="Department" icon="fa-building-user" :value="$job->department" placeholder="e.g. Sciences" />
                <x-select-field name="employment_type" label="Employment type" icon="fa-clock" :options="$typeOptions" :selected="$job->employment_type?->value" required />
                <x-text-field name="location" label="Job location" icon="fa-location-dot" :value="$job->location" placeholder="e.g. Main campus, Ikeja" />
                <x-text-field name="openings" type="number" label="Number of openings" icon="fa-users" :value="$job->openings" min="1" max="999" placeholder="Optional" />
                <x-text-field name="salary_range" label="Salary / salary range" icon="fa-money-bill-wave" :value="$job->salary_range" placeholder="e.g. ₦150,000 to ₦200,000 per month" helper="Optional. Leave blank to not show a salary." />
                <x-text-field name="closes_at" type="date" label="Application deadline" icon="fa-calendar-day" :value="$job->closes_at?->format('Y-m-d')" required helper="Applications stop at the end of this day." />
                <x-text-field name="experience_required" label="Experience required" icon="fa-chart-line" :value="$job->experience_required" placeholder="e.g. At least 3 years teaching SS1 to SS3" />
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-file-lines text-primary-500" aria-hidden="true"></i>Description</h2>
            <div class="mt-4 space-y-4">
                <x-textarea-field name="description" label="Job description" rows="6" :value="$job->description" placeholder="What the role is, who it reports to, and what makes it a good place to work." required />
                <x-textarea-field name="responsibilities" label="Responsibilities" rows="5" :value="$job->responsibilities" placeholder="One per line, e.g.&#10;Teach Mathematics from SS1 to SS3&#10;Prepare lesson notes and assessments" />
                <x-textarea-field name="requirements" label="Requirements" rows="5" :value="$job->requirements" placeholder="One per line" />
                <x-textarea-field name="qualifications" label="Qualifications" rows="4" :value="$job->qualifications" placeholder="e.g. B.Sc./B.Ed. in Mathematics; TRCN registration" />
            </div>
        </section>

        <section class="{{ $card }}">
            <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-paper-plane text-primary-500" aria-hidden="true"></i>How to apply</h2>
            <div class="mt-4 space-y-4">
                <x-textarea-field name="application_instructions" label="Application instructions" rows="3" :value="$job->application_instructions" placeholder="Anything applicants should know or prepare before applying." helper="Shown beside the application form. Applicants always apply through the form on your Job Portal." />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-text-field name="contact_name" label="Contact person" icon="fa-user" :value="$job->contact_name" placeholder="Optional" />
                    <x-text-field name="contact_email" type="email" label="Contact email" icon="fa-envelope" :value="$job->contact_email" :placeholder="$contact->email ?: 'Optional'" />
                    <x-text-field name="contact_phone" type="tel" label="Contact phone" icon="fa-phone" :value="$job->contact_phone" :placeholder="$contact->phone ?: 'Optional'" />
                </div>
            </div>
        </section>

        {{-- The job image: on the portal, in the share image and the link preview. --}}
        <section class="{{ $card }}" x-data="{ preview: @js($job->featuredImageUrl()), remove: false }">
            <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-image text-primary-500" aria-hidden="true"></i>Job image</h2>
            <p class="mt-1 text-[12.5px] text-gray-500 dark:text-gray-400">Shown at the top of the vacancy, in the image you download to share, and in link previews on WhatsApp, Facebook and LinkedIn. A wide photo works best.</p>

            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                <div class="flex aspect-[16/9] w-full shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-dashed border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-900 sm:w-56">
                    <template x-if="preview && ! remove"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                    <template x-if="! preview || remove"><i class="fa-solid fa-image text-[26px] text-gray-300" aria-hidden="true"></i></template>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="featured_image" class="field-label">Upload image</label>
                    <input
                        id="featured_image"
                        type="file"
                        name="featured_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="mt-1 w-full @error('featured_image') field-invalid @enderror"
                        x-on:change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false }"
                    >
                    @error('featured_image')
                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                    @else
                        <small class="field-hint mt-1">JPG, PNG or WebP, up to 15MB. Large photos are resized automatically.</small>
                    @enderror

                    @if ($job->featured_image_path)
                        <label class="mt-3 flex items-center gap-2 text-[13px] text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="remove_featured_image" value="1" x-model="remove">
                            Remove the current image
                        </label>
                    @endif
                </div>
            </div>
        </section>

        {{-- Extra questions. --}}
        <section
            class="{{ $card }}"
            x-data="{
                questions: @js($initialQuestions),
                max: {{ $maxQuestions }},
                add() { if (this.questions.length < this.max) this.questions.push({ id: null, question: '', type: 'short_text', options: '', required: false }) },
                remove(index) { this.questions.splice(index, 1) },
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="{{ $sectionTitle }}"><i class="fa-solid fa-circle-question text-primary-500" aria-hidden="true"></i>Extra questions for applicants</h2>
                <button type="button" x-on:click="add()" x-bind:disabled="questions.length >= max" class="inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-[12.5px] font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add question
                </button>
            </div>
            <p class="mt-1 text-[12.5px] text-gray-500 dark:text-gray-400">Optional. Every applicant is already asked for their name, contact details, qualifications, experience, cover letter and CV.</p>

            <template x-if="questions.length === 0">
                <p class="mt-4 rounded-[8px] bg-gray-50 p-4 text-center text-[13px] text-gray-500 dark:bg-gray-900 dark:text-gray-400">No extra questions.</p>
            </template>

            <div class="mt-4 space-y-3">
                <template x-for="(question, index) in questions" :key="index">
                    <div class="rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                        <input type="hidden" :name="`questions[${index}][id]`" :value="question.id ?? ''">
                        <div class="grid gap-3 sm:grid-cols-[1fr_190px]">
                            <div>
                                <label class="field-label" :for="`question_${index}`">Question <span class="text-red-500">*</span></label>
                                <input type="text" class="mt-1" :id="`question_${index}`" :name="`questions[${index}][question]`" x-model="question.question" maxlength="255" required placeholder="e.g. Are you TRCN registered?">
                            </div>
                            <div>
                                <label class="field-label" :for="`question_type_${index}`">Answer type</label>
                                <select class="mt-1" :id="`question_type_${index}`" :name="`questions[${index}][type]`" x-model="question.type">
                                    @foreach ($questionTypeOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-3" x-show="question.type === 'choice'" x-cloak>
                            <label class="field-label" :for="`question_options_${index}`">Options <span class="text-red-500">*</span></label>
                            <input type="text" class="mt-1" :id="`question_options_${index}`" :name="`questions[${index}][options]`" x-model="question.options" :required="question.type === 'choice'" placeholder="Separate with commas, e.g. Primary, Junior Secondary, Senior Secondary">
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-[13px] text-gray-700 dark:text-gray-300">
                                <input type="hidden" :name="`questions[${index}][required]`" value="0">
                                <input type="checkbox" :name="`questions[${index}][required]`" value="1" x-model="question.required">
                                Applicants must answer
                            </label>
                            <button type="button" x-on:click="remove(index)" class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-red-600 hover:text-red-700">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                            </button>
                        </div>
                    </div>
                </template>
            </div>
            @error('questions')<p class="mt-2 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
            @foreach ($errors->get('questions.*') as $messages)
                <p class="mt-2 text-[11px] font-medium text-red-600">{{ $messages[0] }}</p>
            @endforeach
        </section>

        <div class="sticky bottom-0 z-10 -mx-4 flex flex-col gap-2 border-t border-gray-200 bg-white/95 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/95 sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0">
            <button type="submit" name="intent" value="save" x-bind:disabled="submitting" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-[8px] border border-gray-300 bg-white px-5 text-[13px] font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                {{ $editing ? 'Save changes' : 'Save as draft' }}
            </button>
            @if (! $editing || $job->status !== \App\Enums\JobPostingStatus::Published)
                <button type="submit" name="intent" value="publish" x-bind:disabled="submitting" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-5 text-[13px] font-bold text-white hover:bg-primary-700 disabled:opacity-60">
                    <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                    {{ $editing ? 'Save & publish' : 'Publish vacancy' }}
                </button>
            @endif
        </div>
    </form>
</x-dashboard-layout>
