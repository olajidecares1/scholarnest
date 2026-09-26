@php
    $statusOptions = ['' => 'Active vacancies (not archived)'] + collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp

<x-dashboard-layout page-title="Recruitment" page-subtitle="Create vacancies, share them, and manage applicants.">
    <div class="space-y-6">
        @include('school-admin.careers._flash')

        {{-- At a glance. --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['Open vacancies', $stats['open'], 'fa-briefcase', null],
                ['Applications', $stats['applications'], 'fa-users', route('careers.applications.index')],
                ['New to review', $stats['new'], 'fa-inbox', route('careers.applications.index', ['status' => 'new'])],
                ['Invited to interview', $stats['interviews'], 'fa-calendar-check', route('careers.applications.index', ['status' => 'interview_invited'])],
            ] as [$label, $value, $icon, $link])
                <a @if ($link) href="{{ $link }}" @endif class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm transition dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px] {{ $link ? 'hover:border-primary-300' : '' }}">
                    <small class="flex items-center gap-2 text-[12px] font-semibold text-gray-500 dark:text-gray-400">
                        <i class="fa-solid {{ $icon }} text-primary-500" aria-hidden="true"></i>{{ $label }}
                    </small>
                    <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($value) }}</p>
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $school->publicUrl('public.jobs.index') }}" target="_blank" rel="noopener" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-3 py-2 text-[13px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    View your Job Portal
                </a>
                <a href="{{ route('careers.applications.index') }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-3 py-2 text-[13px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    All applications
                </a>
            </div>
            <a href="{{ route('careers.create') }}" class="btn inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-[13px] font-bold text-white hover:bg-primary-700">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Create vacancy
            </a>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('careers.index') }}" class="grid gap-3 border-b border-gray-100 p-4 dark:border-gray-700 sm:grid-cols-[1fr_260px_auto]">
                <x-text-field name="q" icon="fa-magnifying-glass" :value="$filters['q'] ?? null" placeholder="Search vacancies by title" aria-label="Search vacancies" />
                <x-select-field name="status" icon="fa-filter" :options="$statusOptions" :selected="$filters['status'] ?? ''" aria-label="Status" />
                <button type="submit" class="btn inline-flex h-[var(--field-height)] items-center justify-center gap-2 rounded-[8px] border border-gray-300 px-4 text-[13px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Filter
                </button>
            </form>

            @if ($jobs->isEmpty())
                <div class="p-10 text-center">
                    <i class="fa-solid fa-briefcase text-[28px] text-gray-300" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">No vacancies yet.</p>
                    <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Create your first vacancy, publish it, and share its link to start receiving applications.</small>
                </div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($jobs as $job)
                        <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('careers.show', $job) }}" class="truncate text-[15px] font-bold text-gray-900 hover:text-primary-700 dark:text-white">{{ $job->title }}</a>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $job->status->badgeClasses() }}">
                                        <i class="fa-solid {{ $job->status->icon() }}" aria-hidden="true"></i>{{ $job->status->label() }}
                                    </span>
                                    @if ($job->status === \App\Enums\JobPostingStatus::Published && $job->deadlinePassed())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                            <i class="fa-solid fa-hourglass-end" aria-hidden="true"></i>Deadline passed
                                        </span>
                                    @endif
                                </div>
                                <small class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] text-gray-500 dark:text-gray-400">
                                    <span><i class="fa-solid fa-clock mr-1" aria-hidden="true"></i>{{ $job->employment_type->label() }}</span>
                                    @if ($job->department)<span><i class="fa-solid fa-building-user mr-1" aria-hidden="true"></i>{{ $job->department }}</span>@endif
                                    @if ($job->closes_at)<span><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>Closes {{ $job->closes_at->format('j M Y') }}</span>@endif
                                </small>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a href="{{ route('careers.show', $job) }}" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-primary-50 px-3 py-1.5 text-[12.5px] font-bold text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    {{ $job->applications_count }} {{ Str::plural('applicant', $job->applications_count) }}
                                    @if ($job->new_applications_count)
                                        <span class="rounded-full bg-primary-600 px-1.5 text-[10.5px] text-white">{{ $job->new_applications_count }} new</span>
                                    @endif
                                </a>
                                <a href="{{ route('careers.edit', $job) }}" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-[12.5px] font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $jobs->links() }}</div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
