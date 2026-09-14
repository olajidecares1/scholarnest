@php
    $job->setRelation('school', $school);
    $contact = \App\Support\SchoolContact::for($school);
    $accepting = $job->acceptsApplications();
    $submittedName = session('job_application_submitted');
    $jobUrl = $job->publicUrl();
    $previewImage = $school->publicUrl('public.jobs.preview-image', ['token' => $job->public_token]);
    $mailTo = $job->contact_email ?: $contact->email;
    $phone = $job->contact_phone ?: $contact->phone;

    $sections = collect([
        ['Job description', 'fa-file-lines', $job->description],
        ['Responsibilities', 'fa-list-check', $job->responsibilities],
        ['Requirements', 'fa-clipboard-check', $job->requirements],
        ['Qualifications', 'fa-graduation-cap', $job->qualifications],
    ])->filter(fn ($section) => filled($section[2]));
@endphp

<x-job-portal-layout
    :school="$school"
    :title="$job->title"
    :description="$job->summary(200)"
    :image="$preview ? null : $previewImage"
    :canonical="$jobUrl"
>
    @if ($preview)
        <div class="sticky top-0 z-30 bg-amber-400 px-4 py-2 text-center text-[13px] font-bold text-amber-950">
            <i class="fa-solid fa-eye mr-1" aria-hidden="true"></i>
            Preview: this is how applicants see this vacancy. Status: {{ $job->status->label() }}.
            <a href="{{ route('careers.show', $job) }}" class="ml-2 underline">Back to dashboard</a>
        </div>
    @endif

    <article class="mx-auto max-w-5xl px-4 pt-6 sm:px-6 sm:pt-8">
        <a href="{{ $school->publicUrl('public.jobs.index') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary-700 hover:text-primary-800">
            <i class="fa-solid fa-arrow-left text-[11px]" aria-hidden="true"></i>
            All vacancies at {{ $school->name }}
        </a>

        <div class="mt-4 overflow-hidden rounded-[14px] border border-[#E1E8F2] bg-white shadow-sm">
            @if ($job->featuredImageUrl())
                <img src="{{ $job->featuredImageUrl() }}" alt="{{ $job->title }}" class="max-h-[360px] w-full object-cover">
            @endif

            <div class="p-5 sm:p-8">
                <div class="flex flex-wrap items-center gap-2">
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-bold',
                        'bg-green-100 text-green-800' => $accepting,
                        'bg-amber-100 text-amber-900' => ! $accepting,
                    ])>
                        <i class="fa-solid {{ $accepting ? 'fa-circle-check' : 'fa-lock' }}" aria-hidden="true"></i>
                        {{ $job->publicStateLabel() }}
                    </span>
                    @if ($job->openings)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 text-[11.5px] font-bold text-primary-700">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            {{ $job->openings }} {{ Str::plural('opening', $job->openings) }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-3 text-[24px] font-extrabold leading-tight tracking-tight sm:text-[30px]">{{ $job->title }}</h1>
                <p class="mt-1 text-[14px] font-semibold text-[#5B7099]">{{ $school->name }}</p>

                <dl class="mt-5 grid gap-3 text-[13.5px] sm:grid-cols-2 lg:grid-cols-3">
                    <div class="flex gap-2.5"><dt class="sr-only">Employment type</dt><i class="fa-solid fa-clock mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>{{ $job->employment_type->label() }}</dd></div>
                    @if ($job->department)
                        <div class="flex gap-2.5"><dt class="sr-only">Department</dt><i class="fa-solid fa-building-user mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>{{ $job->department }}</dd></div>
                    @endif
                    @if ($job->location)
                        <div class="flex gap-2.5"><dt class="sr-only">Location</dt><i class="fa-solid fa-location-dot mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>{{ $job->location }}</dd></div>
                    @endif
                    @if ($job->salary_range)
                        <div class="flex gap-2.5"><dt class="sr-only">Salary</dt><i class="fa-solid fa-money-bill-wave mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>{{ $job->salary_range }}</dd></div>
                    @endif
                    @if ($job->experience_required)
                        <div class="flex gap-2.5"><dt class="sr-only">Experience</dt><i class="fa-solid fa-briefcase mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>{{ $job->experience_required }}</dd></div>
                    @endif
                    @if ($job->closes_at)
                        <div class="flex gap-2.5"><dt class="sr-only">Application deadline</dt><i class="fa-regular fa-calendar mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dd>Apply by <strong>{{ $job->closes_at->format('l, j F Y') }}</strong></dd></div>
                    @endif
                </dl>

                @if ($accepting && ! $submittedName)
                    <a href="#application" class="mt-6 inline-flex h-[42px] w-full items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-6 text-[14px] font-bold text-white hover:bg-primary-700 sm:w-auto">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Apply for this position
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
            <div class="min-w-0 space-y-4">
                @foreach ($sections as [$heading, $icon, $body])
                    <section class="rounded-[14px] border border-[#E1E8F2] bg-white p-5 sm:p-7">
                        <h2 class="flex items-center gap-2 text-[16px] font-bold">
                            <i class="fa-solid {{ $icon }} text-primary-500" aria-hidden="true"></i>
                            {{ $heading }}
                        </h2>
                        <div class="mt-3 whitespace-pre-line break-words text-[14px] leading-relaxed text-[#3D5378]">{{ $body }}</div>
                    </section>
                @endforeach
            </div>

            <aside class="min-w-0 space-y-4">
                <section class="rounded-[14px] border border-[#E1E8F2] bg-white p-5">
                    <h2 class="flex items-center gap-2 text-[14px] font-bold"><i class="fa-solid fa-circle-info text-primary-500" aria-hidden="true"></i>How to apply</h2>
                    <p class="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-[#3D5378]">{{ $job->application_instructions ?: 'Complete the application form below and attach your CV. You will receive a confirmation email once it is submitted.' }}</p>
                </section>

                @if ($job->contact_name || $mailTo || $phone)
                    <section class="rounded-[14px] border border-[#E1E8F2] bg-white p-5 text-[13px]">
                        <h2 class="flex items-center gap-2 text-[14px] font-bold"><i class="fa-solid fa-address-card text-primary-500" aria-hidden="true"></i>Contact</h2>
                        @if ($job->contact_name)
                            <p class="mt-2 flex gap-2"><i class="fa-solid fa-user mt-0.5 w-4 text-primary-500" aria-hidden="true"></i>{{ $job->contact_name }}</p>
                        @endif
                        @if ($mailTo)
                            <p class="mt-1.5 flex gap-2"><i class="fa-solid fa-envelope mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><a href="mailto:{{ $mailTo }}" class="min-w-0 break-all text-primary-700">{{ $mailTo }}</a></p>
                        @endif
                        @if ($phone)
                            <p class="mt-1.5 flex gap-2"><i class="fa-solid fa-phone mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-primary-700">{{ $phone }}</a></p>
                        @endif
                    </section>
                @endif
            </aside>
        </div>

        {{-- The application. --}}
        <section id="application" class="mt-6 scroll-mt-16 rounded-[14px] border border-[#E1E8F2] bg-white p-5 sm:p-8">
            @if ($submittedName)
                <div class="text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-[24px] text-green-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                    <h2 class="mt-3 text-[20px] font-extrabold">Application submitted</h2>
                    <p class="mx-auto mt-2 max-w-md text-[14px] leading-relaxed text-[#5B7099]">
                        Thank you, {{ $submittedName }}. Your application for <strong>{{ $job->title }}</strong> has been sent to {{ $school->name }}.
                        A confirmation has been emailed to you, and the school will contact you if you are shortlisted.
                    </p>
                    <a href="{{ $school->publicUrl('public.jobs.index') }}" class="mt-5 inline-flex items-center gap-2 text-[13px] font-bold text-primary-700">
                        <i class="fa-solid fa-list" aria-hidden="true"></i> See other vacancies
                    </a>
                </div>
            @elseif (! $accepting && ! $preview)
                <div class="text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-[22px] text-amber-800"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                    <h2 class="mt-3 text-[20px] font-extrabold">Applications are closed</h2>
                    <p class="mx-auto mt-2 max-w-md text-[14px] text-[#5B7099]">{{ session('job_application_error') ?? 'This vacancy is no longer accepting applications.' }}</p>
                    <a href="{{ $school->publicUrl('public.jobs.index') }}" class="mt-5 inline-flex items-center gap-2 text-[13px] font-bold text-primary-700">
                        <i class="fa-solid fa-list" aria-hidden="true"></i> See open vacancies
                    </a>
                </div>
            @else
                <h2 class="flex items-center gap-2 text-[20px] font-extrabold"><i class="fa-solid fa-paper-plane text-primary-500" aria-hidden="true"></i>Apply for {{ $job->title }}</h2>
                <p class="mt-1 text-[13px] text-[#5B7099]">Fields marked <span class="text-red-500">*</span> are required. Your details are sent only to {{ $school->name }}.</p>

                @if ($errors->any())
                    <div class="mt-4 rounded-[8px] border border-red-200 bg-red-50 p-3 text-[13px] text-red-800" role="alert">
                        <p class="font-bold"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Please check the highlighted fields.</p>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ $preview ? '#' : $school->publicUrl('public.jobs.apply', ['token' => $job->public_token]) }}"
                    enctype="multipart/form-data"
                    class="mt-5 space-y-4"
                    x-data="{ submitting: false }"
                    @if ($preview) x-on:submit.prevent="alert('This is a preview. Applicants will be able to submit this form once the vacancy is published.')" @else x-on:submit="submitting = true" @endif
                >
                    @csrf
                    <x-honeypot />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-text-field name="full_name" label="Full name" icon="fa-user" autocomplete="name" required />
                        <x-text-field name="email" type="email" label="Email address" icon="fa-envelope" autocomplete="email" required />
                        <x-text-field name="phone" type="tel" label="Phone number" icon="fa-phone" autocomplete="tel" placeholder="e.g. 0803 123 4567" required />
                        <x-text-field name="years_of_experience" type="number" label="Years of experience" icon="fa-briefcase" min="0" max="60" required />
                    </div>

                    <x-textarea-field name="address" label="Address" rows="2" placeholder="Your home or postal address" />
                    <x-textarea-field name="qualifications" label="Qualifications" rows="3" placeholder="e.g. B.Ed. Mathematics (University of Lagos), TRCN certified" required />
                    <x-textarea-field name="cover_letter" label="Cover letter" rows="6" placeholder="Tell the school why you are a good fit for this position." required />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="cv" class="field-label"><i class="fa-solid fa-file-arrow-up mr-1 text-primary-500" aria-hidden="true"></i>CV / Résumé<span class="text-red-500"> *</span></label>
                            <input id="cv" type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required class="mt-1 w-full @error('cv') field-invalid @enderror">
                            @error('cv')<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@else<small class="field-hint mt-1">PDF or Word, up to 5MB.</small>@enderror
                        </div>
                        <div>
                            <label for="documents" class="field-label"><i class="fa-solid fa-paperclip mr-1 text-primary-500" aria-hidden="true"></i>Supporting documents</label>
                            <input id="documents" type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="mt-1 w-full @error('documents') field-invalid @enderror @error('documents.*') field-invalid @enderror">
                            @error('documents')<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
                            @error('documents.*')<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@else<small class="field-hint mt-1">Optional. Certificates or references, up to 5 files of 5MB each.</small>@enderror
                        </div>
                    </div>

                    @if ($job->questions->isNotEmpty())
                        <fieldset class="space-y-4 rounded-[10px] border border-[#E1E8F2] p-4">
                            <legend class="px-1 text-[13px] font-bold"><i class="fa-solid fa-circle-question mr-1 text-primary-500" aria-hidden="true"></i>A few questions from the school</legend>

                            @foreach ($job->questions as $question)
                                @php $field = "answers[{$question->id}]"; $errorKey = $question->fieldName(); @endphp
                                @switch($question->type)
                                    @case(\App\Enums\JobQuestionType::LongText)
                                        <div>
                                            <label for="answer_{{ $question->id }}" class="field-label">{{ $question->question }}@if ($question->is_required)<span class="text-red-500"> *</span>@endif</label>
                                            <textarea id="answer_{{ $question->id }}" name="{{ $field }}" rows="3" @if ($question->is_required) required @endif class="mt-1 @error($errorKey) field-invalid @enderror">{{ old($errorKey) }}</textarea>
                                            @error($errorKey)<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        @break
                                    @case(\App\Enums\JobQuestionType::YesNo)
                                    @case(\App\Enums\JobQuestionType::Choice)
                                        <div>
                                            <label for="answer_{{ $question->id }}" class="field-label">{{ $question->question }}@if ($question->is_required)<span class="text-red-500"> *</span>@endif</label>
                                            <select id="answer_{{ $question->id }}" name="{{ $field }}" @if ($question->is_required) required @endif class="mt-1 @error($errorKey) field-invalid @enderror">
                                                <option value="">Select an answer</option>
                                                @foreach ($question->choices() as $choice)
                                                    <option value="{{ $choice }}" @selected(old($errorKey) === $choice)>{{ $choice }}</option>
                                                @endforeach
                                            </select>
                                            @error($errorKey)<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        @break
                                    @default
                                        <div>
                                            <label for="answer_{{ $question->id }}" class="field-label">{{ $question->question }}@if ($question->is_required)<span class="text-red-500"> *</span>@endif</label>
                                            <input id="answer_{{ $question->id }}" type="text" name="{{ $field }}" value="{{ old($errorKey) }}" @if ($question->is_required) required @endif class="mt-1 @error($errorKey) field-invalid @enderror">
                                            @error($errorKey)<p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                @endswitch
                            @endforeach
                        </fieldset>
                    @endif

                    <button
                        type="submit"
                        x-bind:disabled="submitting"
                        class="inline-flex h-[44px] w-full items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-6 text-[14px] font-bold text-white hover:bg-primary-700 disabled:cursor-wait disabled:opacity-70 sm:w-auto"
                    >
                        <i class="fa-solid" :class="submitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'" aria-hidden="true"></i>
                        <span x-text="submitting ? 'Sending your application…' : 'Submit application'">Submit application</span>
                    </button>
                </form>
            @endif
        </section>
    </article>

    {{-- On a phone the apply button follows the reader down a long description. --}}
    @if ($accepting && ! $submittedName)
        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-[#E1E8F2] bg-white/95 p-3 sm:hidden">
            <a href="#application" class="flex h-[44px] items-center justify-center gap-2 rounded-[8px] bg-primary-600 text-[14px] font-bold text-white">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Apply now
            </a>
        </div>
    @endif
</x-job-portal-layout>
