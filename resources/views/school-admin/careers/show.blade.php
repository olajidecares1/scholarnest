@php
    use App\Enums\JobPostingStatus;

    $jobUrl = $job->publicUrl();
    $shareText = "We're hiring: {$job->title} at {$job->school->name}. Apply here:";
    $encodedUrl = rawurlencode($jobUrl);
    $encodedText = rawurlencode($shareText);
    $shareable = $job->status === JobPostingStatus::Published;
    $button = 'inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-2 text-[12.5px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700';
    $card = 'rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 lg:rounded-[10px]';

    $shareTargets = [
        ['WhatsApp', 'fa-brands fa-whatsapp', 'https://wa.me/?text='.$encodedText.'%20'.$encodedUrl, 'bg-[#25D366]'],
        ['Facebook', 'fa-brands fa-facebook-f', 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl, 'bg-[#1877F2]'],
        ['X', 'fa-brands fa-x-twitter', 'https://twitter.com/intent/tweet?text='.$encodedText.'&url='.$encodedUrl, 'bg-black'],
        ['LinkedIn', 'fa-brands fa-linkedin-in', 'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl, 'bg-[#0A66C2]'],
        ['Telegram', 'fa-brands fa-telegram', 'https://t.me/share/url?url='.$encodedUrl.'&text='.$encodedText, 'bg-[#229ED9]'],
        ['Email', 'fa-solid fa-envelope', 'mailto:?subject='.rawurlencode("Vacancy: {$job->title} at {$job->school->name}").'&body='.$encodedText.'%20'.$encodedUrl, 'bg-gray-700'],
    ];

    $statusFilterUrl = fn (?string $status) => route('careers.show', array_filter(['job' => $job, 'status' => $status]));
@endphp

<x-dashboard-layout :page-title="$job->title" page-subtitle="Vacancy and applicants">
    <div class="space-y-6">
        <a href="{{ route('careers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
            <i class="fa-solid fa-arrow-left text-[12px]" aria-hidden="true"></i> Back to recruitment
        </a>

        @include('school-admin.careers._flash')

        <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
            <div class="min-w-0 space-y-6">
                {{-- Summary and actions. --}}
                <section class="{{ $card }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-bold {{ $job->status->badgeClasses() }}">
                            <i class="fa-solid {{ $job->status->icon() }}" aria-hidden="true"></i>{{ $job->status->label() }}
                        </span>
                        @if ($job->status === JobPostingStatus::Published)
                            <span class="text-[12.5px] font-semibold {{ $job->acceptsApplications() ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                {{ $job->publicStateLabel() }}
                            </span>
                        @endif
                    </div>
                    <h2 class="mt-2 text-xl font-extrabold text-gray-900 dark:text-white">{{ $job->title }}</h2>
                    <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-gray-500 dark:text-gray-400">
                        <span><i class="fa-solid fa-clock mr-1" aria-hidden="true"></i>{{ $job->employment_type->label() }}</span>
                        @if ($job->department)<span><i class="fa-solid fa-building-user mr-1" aria-hidden="true"></i>{{ $job->department }}</span>@endif
                        @if ($job->location)<span><i class="fa-solid fa-location-dot mr-1" aria-hidden="true"></i>{{ $job->location }}</span>@endif
                        @if ($job->salary_range)<span><i class="fa-solid fa-money-bill-wave mr-1" aria-hidden="true"></i>{{ $job->salary_range }}</span>@endif
                        @if ($job->closes_at)<span><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>Closes {{ $job->closes_at->format('j M Y') }}</span>@endif
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ route('careers.edit', $job) }}" class="{{ $button }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                        <a href="{{ route('careers.preview', $job) }}" target="_blank" rel="noopener" class="{{ $button }}"><i class="fa-solid fa-eye" aria-hidden="true"></i> Preview</a>

                        @if (in_array($job->status, [JobPostingStatus::Draft], true))
                            <form method="POST" action="{{ route('careers.publish', $job) }}">@csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-3 py-2 text-[12.5px] font-bold text-white hover:bg-primary-700"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Publish</button>
                            </form>
                        @endif

                        @if ($job->status === JobPostingStatus::Published)
                            <form method="POST" action="{{ route('careers.close', $job) }}">@csrf
                                <button type="submit" class="{{ $button }}"><i class="fa-solid fa-lock" aria-hidden="true"></i> Close applications</button>
                            </form>
                            <form method="POST" action="{{ route('careers.unpublish', $job) }}">@csrf
                                <button type="submit" class="{{ $button }}"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish</button>
                            </form>
                        @endif

                        @if (in_array($job->status, [JobPostingStatus::Closed, JobPostingStatus::Archived], true))
                            <form method="POST" action="{{ route('careers.reopen', $job) }}">@csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-3 py-2 text-[12.5px] font-bold text-white hover:bg-primary-700"><i class="fa-solid fa-lock-open" aria-hidden="true"></i> Reopen</button>
                            </form>
                        @endif

                        @if ($job->status !== JobPostingStatus::Archived)
                            <form method="POST" action="{{ route('careers.archive', $job) }}" onsubmit="return confirm('Archive this vacancy? It will be removed from your Job Portal. Its applications are kept.');">@csrf
                                <button type="submit" class="{{ $button }}"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archive</button>
                            </form>
                        @endif

                        @if ($job->applications_count === 0)
                            <form method="POST" action="{{ route('careers.destroy', $job) }}" onsubmit="return confirm('Delete this vacancy permanently?');">@csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-[8px] border border-red-300 px-3 py-2 text-[12.5px] font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete</button>
                            </form>
                        @endif
                    </div>

                    {{-- Extend the deadline without opening the whole form. --}}
                    <form method="POST" action="{{ route('careers.extend', $job) }}" class="mt-5 grid gap-2 border-t border-gray-100 pt-4 dark:border-gray-700 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <x-text-field name="closes_at" type="date" label="Extend or change the application deadline" icon="fa-calendar-plus" :value="$job->closes_at?->format('Y-m-d')" :min="today()->format('Y-m-d')" required />
                        <button type="submit" class="inline-flex h-[var(--field-height)] items-center justify-center gap-2 rounded-[8px] border border-gray-300 px-4 text-[13px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                            <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Update deadline
                        </button>
                    </form>
                </section>

                {{-- Applicants. --}}
                <section class="{{ $card }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-users text-primary-500" aria-hidden="true"></i>Applicants ({{ $job->applications_count }})</h2>
                        <a href="{{ route('careers.applications.index', ['job' => $job->uuid]) }}" class="text-[12.5px] font-semibold text-primary-700 hover:underline">Search &amp; filter</a>
                    </div>

                    {{-- Recruitment progress at a glance, each a filter. --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ $statusFilterUrl(null) }}" class="rounded-full px-3 py-1 text-[12px] font-semibold {{ blank($filters['status'] ?? null) ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">All {{ $job->applications_count }}</a>
                        @foreach ($applicationStatuses as $status)
                            @if (($statusCounts[$status->value] ?? 0) > 0)
                                <a href="{{ $statusFilterUrl($status->value) }}" class="rounded-full px-3 py-1 text-[12px] font-semibold {{ ($filters['status'] ?? null) === $status->value ? 'bg-primary-600 text-white' : $status->badgeClasses() }}">
                                    {{ $status->label() }} {{ $statusCounts[$status->value] }}
                                </a>
                            @endif
                        @endforeach
                    </div>

                    @if ($applications->isEmpty())
                        <p class="mt-4 rounded-[8px] bg-gray-50 p-6 text-center text-[13px] text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            {{ $job->applications_count ? 'No applicants with this status.' : ($shareable ? 'No applications yet. Share the link to reach applicants.' : 'No applications yet.') }}
                        </p>
                    @else
                        <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($applications as $application)
                                <li>
                                    <a href="{{ route('careers.applications.show', $application) }}" class="flex items-center gap-3 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $application->initials() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[14px] font-bold text-gray-900 dark:text-white">{{ $application->full_name }}</span>
                                            <span class="block truncate text-[12px] text-gray-500 dark:text-gray-400">{{ $application->email }} · {{ $application->years_of_experience }} yrs · {{ $application->created_at->diffForHumans() }}</span>
                                        </span>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $application->status->badgeClasses() }}">
                                            <i class="fa-solid {{ $application->status->icon() }}" aria-hidden="true"></i><span class="hidden sm:inline">{{ $application->status->label() }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3">{{ $applications->links() }}</div>
                    @endif
                </section>
            </div>

            {{-- Share. --}}
            <aside class="min-w-0 space-y-6">
                <section class="{{ $card }}" x-data="{
                    copied: false,
                    async copy() {
                        try { await navigator.clipboard.writeText(@js($jobUrl)); }
                        catch (e) { this.$refs.link.select(); document.execCommand('copy'); }
                        this.copied = true; setTimeout(() => this.copied = false, 2000);
                    },
                    canShare: typeof navigator !== 'undefined' && !! navigator.share,
                    share() { navigator.share({ title: @js($job->title.' at '.$job->school->name), text: @js($shareText), url: @js($jobUrl) }).catch(() => {}) },
                }">
                    <h2 class="flex items-center gap-2 text-[15px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-share-nodes text-primary-500" aria-hidden="true"></i>Share this vacancy</h2>

                    @unless ($shareable)
                        <p class="mt-2 rounded-[8px] bg-amber-50 p-3 text-[12.5px] text-amber-900 dark:bg-amber-900/30 dark:text-amber-300">
                            <i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>
                            {{ $job->status === JobPostingStatus::Closed ? 'This vacancy is closed. Its link shows that applications have ended.' : 'Publish this vacancy before sharing it. Until then its link will not open for applicants.' }}
                        </p>
                    @endunless

                    <label for="job-link" class="field-label mt-4">Application link</label>
                    <div class="mt-1 flex gap-2">
                        <input id="job-link" x-ref="link" type="text" readonly value="{{ $jobUrl }}" class="min-w-0 flex-1" x-on:focus="$event.target.select()">
                        <button type="button" x-on:click="copy()" class="inline-flex h-[var(--field-height)] shrink-0 items-center gap-1.5 rounded-[8px] bg-primary-600 px-3 text-[12.5px] font-bold text-white hover:bg-primary-700">
                            <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                    <small class="field-hint mt-1">Opens this vacancy on your Job Portal, and nowhere else.</small>

                    <button type="button" x-show="canShare" x-cloak x-on:click="share()" class="mt-4 inline-flex h-[40px] w-full items-center justify-center gap-2 rounded-[8px] bg-gray-900 text-[13px] font-bold text-white hover:bg-gray-800 dark:bg-gray-700">
                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Share… (Instagram, WhatsApp and more)
                    </button>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach ($shareTargets as [$name, $icon, $href, $colour])
                            <a href="{{ $href }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-1 rounded-[8px] border border-gray-200 p-2 text-[11.5px] font-semibold text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-white {{ $colour }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                                {{ $name }}
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <p class="text-[13px] font-bold text-gray-900 dark:text-white"><i class="fa-solid fa-image mr-1 text-primary-500" aria-hidden="true"></i>Job post image</p>
                        <p class="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                            For Instagram, WhatsApp Status and Facebook posts. It shows your logo, school name and address, the job, and this vacancy's link with a QR code, so anyone who only sees the picture can still apply.
                        </p>
                        <a href="{{ route('careers.share-image', $job) }}" class="mt-3 inline-flex h-[40px] w-full items-center justify-center gap-2 rounded-[8px] border border-gray-300 text-[13px] font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> Download image
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-dashboard-layout>
