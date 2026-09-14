@php
    use App\Enums\InterviewMode;
    use App\Enums\JobApplicationStatus;

    $job = $application->jobPosting;
    $statusOptions = collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $modeOptions = collect($interviewModes)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all();
    $card = 'rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 lg:rounded-[10px]';
    $heading = 'flex items-center gap-2 text-[15px] font-bold text-gray-900 dark:text-white';
    $local = fn ($date) => $date?->copy()->setTimezone($timezone);
    $readableSize = fn (int $bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB';
@endphp

<x-dashboard-layout :page-title="$application->full_name" :page-subtitle="'Application for '.$job->title">
    <div class="space-y-6">
        <a href="{{ route('careers.show', $job) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
            <i class="fa-solid fa-arrow-left text-[12px]" aria-hidden="true"></i> Back to {{ $job->title }}
        </a>

        @include('school-admin.careers._flash')

        <section class="{{ $card }}">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $application->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $application->full_name }}</h2>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-bold {{ $application->status->badgeClasses() }}">
                            <i class="fa-solid {{ $application->status->icon() }}" aria-hidden="true"></i>{{ $application->status->label() }}
                        </span>
                    </div>
                    <p class="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                        <i class="fa-solid fa-briefcase mr-1" aria-hidden="true"></i>{{ $job->title }}
                        · Applied {{ $local($application->created_at)->format('j M Y, g:i A') }}
                    </p>
                </div>
            </div>

            <dl class="mt-5 grid gap-3 text-[13.5px] sm:grid-cols-2">
                <div class="flex min-w-0 gap-2.5"><i class="fa-solid fa-envelope mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dt class="sr-only">Email</dt><dd class="min-w-0 break-all"><a href="mailto:{{ $application->email }}" class="text-primary-700 hover:underline">{{ $application->email }}</a></dd></div>
                <div class="flex gap-2.5"><i class="fa-solid fa-phone mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dt class="sr-only">Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $application->phone) }}" class="text-primary-700 hover:underline">{{ $application->phone }}</a></dd></div>
                <div class="flex gap-2.5"><i class="fa-solid fa-chart-line mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dt class="sr-only">Experience</dt><dd>{{ $application->years_of_experience }} {{ Str::plural('year', $application->years_of_experience) }} of experience</dd></div>
                @if ($application->address)
                    <div class="flex min-w-0 gap-2.5"><i class="fa-solid fa-location-dot mt-0.5 w-4 text-primary-500" aria-hidden="true"></i><dt class="sr-only">Address</dt><dd class="min-w-0 break-words">{{ $application->address }}</dd></div>
                @endif
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="min-w-0 space-y-6">
                <section class="{{ $card }}">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-graduation-cap text-primary-500" aria-hidden="true"></i>Qualifications</h3>
                    <p class="mt-3 whitespace-pre-line break-words text-[14px] leading-relaxed text-gray-700 dark:text-gray-300">{{ $application->qualifications }}</p>
                </section>

                <section class="{{ $card }}">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-file-lines text-primary-500" aria-hidden="true"></i>Cover letter</h3>
                    <p class="mt-3 whitespace-pre-line break-words text-[14px] leading-relaxed text-gray-700 dark:text-gray-300">{{ $application->cover_letter }}</p>
                </section>

                @if (! empty($application->answers))
                    <section class="{{ $card }}">
                        <h3 class="{{ $heading }}"><i class="fa-solid fa-circle-question text-primary-500" aria-hidden="true"></i>Answers to your questions</h3>
                        <dl class="mt-3 space-y-3 text-[14px]">
                            @foreach ($application->answers as $answer)
                                <div>
                                    <dt class="font-semibold text-gray-900 dark:text-white">{{ $answer['question'] }}</dt>
                                    <dd class="mt-0.5 whitespace-pre-line break-words text-gray-700 dark:text-gray-300">{{ $answer['answer'] ?? 'Not answered' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                <section class="{{ $card }}">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-folder-open text-primary-500" aria-hidden="true"></i>CV and documents</h3>
                    <ul class="mt-3 space-y-2">
                        <li>
                            <a href="{{ route('careers.applications.cv', $application) }}" class="flex items-center gap-3 rounded-[8px] border border-gray-200 p-3 hover:border-primary-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40">
                                <i class="fa-solid fa-file-pdf text-[20px] text-red-500" aria-hidden="true"></i>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13.5px] font-bold text-gray-900 dark:text-white">CV / Résumé</span>
                                    <span class="block truncate text-[12px] text-gray-500 dark:text-gray-400">{{ $application->cv_original_name }} · {{ $readableSize($application->cv_size_bytes) }}</span>
                                </span>
                                <i class="fa-solid fa-download text-primary-600" aria-hidden="true"></i>
                            </a>
                        </li>
                        @foreach ($application->documents as $document)
                            <li>
                                <a href="{{ route('careers.applications.document', $document) }}" class="flex items-center gap-3 rounded-[8px] border border-gray-200 p-3 hover:border-primary-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/40">
                                    <i class="fa-solid fa-paperclip text-[18px] text-gray-500" aria-hidden="true"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13.5px] font-semibold text-gray-900 dark:text-white">{{ $document->original_name }}</span>
                                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">{{ $readableSize($document->size_bytes) }}</span>
                                    </span>
                                    <i class="fa-solid fa-download text-primary-600" aria-hidden="true"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($application->interviews->isNotEmpty())
                    <section class="{{ $card }}">
                        <h3 class="{{ $heading }}"><i class="fa-solid fa-calendar-check text-primary-500" aria-hidden="true"></i>Interviews</h3>
                        <ul class="mt-3 space-y-3">
                            @foreach ($application->interviews as $interview)
                                <li class="rounded-[8px] border border-gray-200 p-3 text-[13px] dark:border-gray-700">
                                    <p class="font-bold text-gray-900 dark:text-white">
                                        <i class="fa-solid {{ $interview->mode->icon() }} mr-1 text-primary-500" aria-hidden="true"></i>
                                        {{ $interview->localScheduledFor()->format('l, j M Y · g:i A') }}
                                    </p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $interview->mode->label() }}: {{ $interview->mode === InterviewMode::Online ? $interview->meeting_link : $interview->location }}</p>
                                    @if ($interview->instructions)<p class="mt-1 whitespace-pre-line text-gray-600 dark:text-gray-300">{{ $interview->instructions }}</p>@endif
                                    <p class="mt-1 text-[12px] {{ $interview->notified_at ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                        <i class="fa-solid {{ $interview->notified_at ? 'fa-envelope-circle-check' : 'fa-triangle-exclamation' }} mr-1" aria-hidden="true"></i>
                                        {{ $interview->notified_at ? 'Invitation emailed '.$local($interview->notified_at)->format('j M Y, g:i A') : 'The invitation email could not be sent' }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="min-w-0 space-y-6">
                {{-- Status. --}}
                <section class="{{ $card }}">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-list-check text-primary-500" aria-hidden="true"></i>Application status</h3>
                    <form method="POST" action="{{ route('careers.applications.status', $application) }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <x-select-field name="status" label="Status" icon="fa-flag" :options="$statusOptions" :selected="$application->status->value" required />
                        <x-textarea-field name="note" label="Note (optional)" rows="2" placeholder="e.g. Strong candidate, check references" />
                        <button type="submit" class="inline-flex h-[40px] w-full items-center justify-center gap-2 rounded-[8px] bg-primary-600 text-[13px] font-bold text-white hover:bg-primary-700">
                            <i class="fa-solid fa-check" aria-hidden="true"></i> Update status
                        </button>
                    </form>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach ([[JobApplicationStatus::Shortlisted, 'fa-list-check', 'Shortlist'], [JobApplicationStatus::Rejected, 'fa-circle-xmark', 'Reject']] as [$quick, $icon, $label])
                            @if ($application->status !== $quick)
                                <form method="POST" action="{{ route('careers.applications.status', $application) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="{{ $quick->value }}">
                                    <button type="submit" class="inline-flex h-[38px] w-full items-center justify-center gap-1.5 rounded-[8px] border text-[12.5px] font-bold {{ $quick === JobApplicationStatus::Rejected ? 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200' }}">
                                        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ $label }}
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </section>

                {{-- Interview invitation. --}}
                <section class="{{ $card }}" x-data="{ mode: @js(old('mode', InterviewMode::Physical->value)) }">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-calendar-plus text-primary-500" aria-hidden="true"></i>Invite to interview</h3>
                    <p class="mt-1 text-[12.5px] text-gray-500 dark:text-gray-400">{{ $application->full_name }} is emailed the details at {{ $application->email }}. Times are in {{ $timezone }}.</p>
                    <form method="POST" action="{{ route('careers.applications.interview', $application) }}" class="mt-4 space-y-3">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <x-text-field name="interview_date" type="date" label="Date" icon="fa-calendar-day" :min="today()->format('Y-m-d')" required />
                            <x-text-field name="interview_time" type="time" label="Time" icon="fa-clock" required />
                        </div>
                        <x-select-field name="mode" label="Interview type" icon="fa-people-arrows" :options="$modeOptions" :selected="old('mode', InterviewMode::Physical->value)" model="mode" required />
                        <div x-show="mode === 'physical'">
                            <x-text-field name="location" label="Location" icon="fa-location-dot" placeholder="e.g. Principal's office, Main campus" />
                        </div>
                        <div x-show="mode === 'online'" x-cloak>
                            <x-text-field name="meeting_link" type="url" label="Meeting link" icon="fa-video" placeholder="https://meet.google.com/..." />
                        </div>
                        <x-textarea-field name="instructions" label="Interview instructions" rows="3" placeholder="e.g. Bring your original certificates and a valid ID." />
                        <x-textarea-field name="message" label="Additional message" rows="2" placeholder="Optional" />
                        <button type="submit" class="inline-flex h-[40px] w-full items-center justify-center gap-2 rounded-[8px] bg-gray-900 text-[13px] font-bold text-white hover:bg-gray-800 dark:bg-gray-700">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send invitation
                        </button>
                    </form>
                </section>

                {{-- History. --}}
                <section class="{{ $card }}">
                    <h3 class="{{ $heading }}"><i class="fa-solid fa-clock-rotate-left text-primary-500" aria-hidden="true"></i>Recruitment progress</h3>
                    <ol class="mt-3 space-y-3 border-l border-gray-200 pl-4 dark:border-gray-700">
                        @foreach ($application->events as $event)
                            <li class="relative text-[12.5px]">
                                <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-primary-500"></span>
                                <p class="font-bold text-gray-900 dark:text-white">{{ $event->to_status?->label() }}</p>
                                <p class="text-gray-500 dark:text-gray-400">{{ $local($event->created_at)->format('j M Y, g:i A') }}@if ($event->user) · {{ $event->user->name }}@endif</p>
                                @if ($event->note)<p class="mt-0.5 text-gray-600 dark:text-gray-300">{{ $event->note }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </aside>
        </div>
    </div>
</x-dashboard-layout>
