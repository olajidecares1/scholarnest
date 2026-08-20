<x-public-site-layout :school="$school" :website="$website" title="Careers">
    <section class="px-4 py-14 sm:px-6">
        <div class="mx-auto max-w-4xl">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-primary-600">Job Portal</p>
                <h1 class="mt-2 text-3xl font-extrabold text-gray-900">Join Our Team</h1>
                <p class="mt-2 text-sm text-gray-500">Explore open roles at {{ $school->name }}.</p>
            </div>

            @if ($jobs->isEmpty())
                <div class="mt-10 rounded-[10px] border border-gray-200 p-10 text-center">
                    <p class="text-sm text-gray-500">There are no open positions right now.</p>
                    @if ($website->contact_email)
                        <p class="mt-2 text-sm text-gray-500">Interested in joining us? Reach out at <a href="mailto:{{ $website->contact_email }}" class="font-semibold text-primary-600 hover:text-primary-700">{{ $website->contact_email }}</a>.</p>
                    @endif
                </div>
            @else
                <div class="mt-10 space-y-4" x-data="{ open: null }">
                    @foreach ($jobs as $job)
                        <div class="rounded-[10px] border border-gray-200 shadow-sm">
                            <button type="button" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}" class="flex w-full items-center justify-between gap-3 p-5 text-left">
                                <div class="min-w-0">
                                    <h2 class="text-base font-bold text-gray-900">{{ $job->title }}</h2>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $job->employment_type->label() }}
                                        @if ($job->department) &middot; {{ $job->department }} @endif
                                        @if ($job->location) &middot; {{ $job->location }} @endif
                                        @if ($job->closes_at) &middot; Closes {{ $job->closes_at->format('M j, Y') }} @endif
                                    </p>
                                </div>
                                <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-300" :class="{ 'rotate-180': open === {{ $loop->index }} }" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                            <div x-show="open === {{ $loop->index }}" x-transition style="display: none;" class="border-t border-gray-100 px-5 py-4">
                                <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ $job->description }}</p>
                                @if ($website->contact_email)
                                    <a href="mailto:{{ $website->contact_email }}?subject=Application: {{ $job->title }}" class="mt-4 inline-flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-700">
                                        Apply via Email
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $jobs->links() }}
                </div>
            @endif
        </div>
    </section>
</x-public-site-layout>
