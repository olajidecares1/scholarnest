<x-job-portal-layout :school="$school" :canonical="$school->publicUrl('public.jobs.index')">
    @php
        $contact = \App\Support\SchoolContact::for($school);
        $typeOptions = ['' => 'All job types'] + collect($employmentTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
        $departmentOptions = ['' => 'All departments'] + $departments->mapWithKeys(fn ($d) => [$d => $d])->all();
        $filtered = filled($filters['q'] ?? null) || filled($filters['department'] ?? null) || filled($filters['type'] ?? null);
    @endphp

    <section class="bg-white">
        <div class="mx-auto max-w-5xl px-4 pb-8 pt-8 sm:px-6 sm:pt-10">
            <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-primary-600">Careers at {{ $school->name }}</p>
            <h1 class="mt-2 text-[26px] font-extrabold leading-tight tracking-tight sm:text-[32px]">Join our team</h1>
            <p class="mt-2 max-w-2xl text-[14px] leading-relaxed text-[#5B7099]">
                Explore open positions at {{ $school->name }} and apply online in a few minutes.
                @if ($contact->address)
                    <span class="mt-1 block"><i class="fa-solid fa-location-dot mr-1 text-primary-500" aria-hidden="true"></i>{{ $contact->address }}</span>
                @endif
            </p>

            <form method="GET" action="{{ $school->publicUrl('public.jobs.index') }}" class="mt-6 grid gap-3 sm:grid-cols-[1fr_200px_180px_auto]">
                <x-text-field name="q" icon="fa-magnifying-glass" :value="$filters['q'] ?? null" placeholder="Search by title, department or location" aria-label="Search vacancies" />
                <x-select-field name="department" icon="fa-building-user" :options="$departmentOptions" :selected="$filters['department'] ?? ''" aria-label="Department" />
                <x-select-field name="type" icon="fa-clock" :options="$typeOptions" :selected="$filters['type'] ?? ''" aria-label="Job type" />
                <button type="submit" class="inline-flex h-[var(--field-height)] items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-5 text-[13px] font-bold text-white hover:bg-primary-700">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    Search
                </button>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-[15px] font-bold">
                {{ $jobs->total() }} open {{ Str::plural('position', $jobs->total()) }}
            </h2>
            @if ($filtered)
                <a href="{{ $school->publicUrl('public.jobs.index') }}" class="text-[12.5px] font-semibold text-primary-700 hover:text-primary-800">
                    <i class="fa-solid fa-xmark mr-1" aria-hidden="true"></i>Clear filters
                </a>
            @endif
        </div>

        @if ($jobs->isEmpty())
            <div class="mt-4 rounded-[12px] border border-[#E1E8F2] bg-white p-8 text-center">
                <i class="fa-solid fa-briefcase text-[28px] text-[#B8C4D9]" aria-hidden="true"></i>
                <p class="mt-3 text-[14px] font-semibold">{{ $filtered ? 'No vacancies match your search.' : 'There are no open positions right now.' }}</p>
                <p class="mt-1 text-[13px] text-[#5B7099]">
                    @if ($contact->email)
                        Interested in working with us? Write to <a href="mailto:{{ $contact->email }}" class="font-semibold text-primary-700">{{ $contact->email }}</a>.
                    @else
                        Please check back soon.
                    @endif
                </p>
            </div>
        @else
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($jobs as $job)
                    @php $job->setRelation('school', $school); @endphp
                    <a href="{{ $job->publicUrl() }}" class="group flex flex-col overflow-hidden rounded-[12px] border border-[#E1E8F2] bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md">
                        @if ($job->featuredImageUrl())
                            <img src="{{ $job->featuredImageUrl() }}" alt="" loading="lazy" class="aspect-[16/7] w-full object-cover">
                        @endif
                        <div class="flex flex-1 flex-col p-4 sm:p-5">
                            <h3 class="text-[16px] font-bold leading-snug group-hover:text-primary-700">{{ $job->title }}</h3>
                            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1.5 text-[12.5px] text-[#5B7099]">
                                <li><i class="fa-solid fa-clock mr-1 text-primary-500" aria-hidden="true"></i>{{ $job->employment_type->label() }}</li>
                                @if ($job->department)
                                    <li><i class="fa-solid fa-building-user mr-1 text-primary-500" aria-hidden="true"></i>{{ $job->department }}</li>
                                @endif
                                @if ($job->location)
                                    <li><i class="fa-solid fa-location-dot mr-1 text-primary-500" aria-hidden="true"></i>{{ $job->location }}</li>
                                @endif
                                @if ($job->salary_range)
                                    <li><i class="fa-solid fa-money-bill-wave mr-1 text-primary-500" aria-hidden="true"></i>{{ $job->salary_range }}</li>
                                @endif
                            </ul>
                            <p class="mt-3 line-clamp-3 text-[13px] leading-relaxed text-[#3D5378]">{{ $job->summary(220) }}</p>
                            <div class="mt-auto flex items-center justify-between gap-3 pt-4">
                                @if ($job->closes_at)
                                    <span class="text-[12px] font-semibold text-[#5B7099]"><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>Apply by {{ $job->closes_at->format('j M Y') }}</span>
                                @else
                                    <span></span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 text-[13px] font-bold text-primary-700">
                                    View &amp; apply <i class="fa-solid fa-arrow-right text-[11px]" aria-hidden="true"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $jobs->links() }}
            </div>
        @endif
    </section>
</x-job-portal-layout>
