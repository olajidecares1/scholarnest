@php
    $jobOptions = ['' => 'All vacancies'] + $jobs->mapWithKeys(fn ($job) => [$job->uuid => $job->title])->all();
    $statusOptions = ['' => 'All statuses'] + collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp

<x-dashboard-layout page-title="Applications" page-subtitle="Everyone who has applied to your vacancies.">
    <div class="space-y-6">
        <a href="{{ route('careers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
            <i class="fa-solid fa-arrow-left text-[12px]" aria-hidden="true"></i> Back to recruitment
        </a>

        @include('school-admin.careers._flash')

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" action="{{ route('careers.applications.index') }}" class="grid gap-3 border-b border-gray-100 p-4 dark:border-gray-700 md:grid-cols-[1fr_220px_200px_auto]">
                <x-text-field name="q" icon="fa-magnifying-glass" :value="$filters['q'] ?? null" placeholder="Search by name, email or phone" aria-label="Search applicants" />
                <x-select-field name="job" icon="fa-briefcase" :options="$jobOptions" :selected="$filters['job'] ?? ''" aria-label="Vacancy" />
                <x-select-field name="status" icon="fa-filter" :options="$statusOptions" :selected="$filters['status'] ?? ''" aria-label="Status" />
                <button type="submit" class="btn inline-flex h-[var(--field-height)] items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-4 text-[13px] font-bold text-white hover:bg-primary-700">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search
                </button>
            </form>

            @if ($applications->isEmpty())
                <div class="p-10 text-center">
                    <i class="fa-solid fa-users text-[28px] text-gray-300" aria-hidden="true"></i>
                    <small class="block mt-3 text-sm text-gray-500 dark:text-gray-400">No applications match.</small>
                </div>
            @else
                {{-- A list, not a wide table: it fits a phone without scrolling sideways. --}}
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($applications as $application)
                        <li>
                            <a href="{{ route('careers.applications.show', $application) }}" class="flex flex-col gap-2 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 sm:flex-row sm:items-center">
                                <span class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $application->initials() }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-[14px] font-bold text-gray-900 dark:text-white">{{ $application->full_name }}</span>
                                        <small class="block truncate text-[12.5px] text-gray-500 dark:text-gray-400"><i class="fa-solid fa-briefcase mr-1" aria-hidden="true"></i>{{ $application->jobPosting?->title }}</small>
                                    </span>
                                </span>
                                <span class="flex flex-wrap items-center gap-x-4 gap-y-1 pl-13 text-[12px] text-gray-500 dark:text-gray-400 sm:pl-0">
                                    <span class="truncate"><i class="fa-solid fa-envelope mr-1" aria-hidden="true"></i>{{ $application->email }}</span>
                                    <span><i class="fa-solid fa-phone mr-1" aria-hidden="true"></i>{{ $application->phone }}</span>
                                    <span><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ $application->created_at->diffForHumans() }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $application->status->badgeClasses() }}">
                                        <i class="fa-solid {{ $application->status->icon() }}" aria-hidden="true"></i>{{ $application->status->label() }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $applications->links() }}</div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
