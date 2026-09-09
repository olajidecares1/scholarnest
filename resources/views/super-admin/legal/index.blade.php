{{-- The platform's own legal documents.

     Not part of the CMS screen, deliberately. Everything there is marketing
     copy; everything here is a document a school has agreed to, and the two
     should not sit in the same list where one gets edited with the care due to
     the other. --}}
<x-super-admin-layout
    page-title="Legal Documents"
    page-subtitle="The Terms, Privacy Policy and the rest, as schools read them at {{ url('/legal') }}"
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[10px] border border-blue-200 bg-blue-50 p-5 dark:border-blue-900/50 dark:bg-blue-900/20">
            <p class="text-sm font-bold text-blue-900 dark:text-blue-300">Changes take effect immediately</p>
            <p class="mt-1 text-[12.5px] leading-[1.7] text-blue-800 dark:text-blue-400">
                There is no draft state and no approval step. The moment you save, every school and every visitor sees
                the new text &mdash; including on the registration form, where schools tick a box agreeing to it.
                Raise the version number when you change anything of substance, so it stays possible to say which
                version a school agreed to.
            </p>
        </div>

        <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($documents as $document)
                    <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $document->title }}</h2>

                                @if ($document->is_published)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        Live
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        Hidden
                                    </span>
                                @endif

                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    v{{ $document->version }}
                                </span>
                            </div>

                            @if ($document->summary)
                                <p class="mt-1 max-w-2xl text-[12.5px] leading-[1.6] text-gray-500 dark:text-gray-400">
                                    {{ $document->summary }}
                                </p>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-gray-500 dark:text-gray-400">
                                <span>/legal/{{ $document->slug }}</span>
                                <span>
                                    Updated {{ $document->updated_at->format('j M Y') }}
                                    @if ($document->updatedBy)
                                        by {{ $document->updatedBy->name }}
                                    @endif
                                </span>
                            </div>

                            {{-- Two counts that matter more than they look.

                                 A published document still carrying notes to
                                 counsel has unresolved legal questions inside
                                 it, and one still carrying [TO BE PROVIDED] is
                                 telling schools that AkademicNest's own address is
                                 yet to be decided. Both are easy to forget once
                                 the page renders and looks finished. --}}
                            @if ($document->internalNoteCount() > 0 || $document->placeholderCount() > 0)
                                <div class="mt-2.5 flex flex-wrap gap-2">
                                    @if ($document->placeholderCount() > 0)
                                        <span class="rounded-[6px] bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                            {{ $document->placeholderCount() }} placeholder{{ $document->placeholderCount() === 1 ? '' : 's' }} still to fill in
                                        </span>
                                    @endif
                                    @if ($document->internalNoteCount() > 0)
                                        <span class="rounded-[6px] bg-purple-100 px-2 py-1 text-[11px] font-semibold text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                            {{ $document->internalNoteCount() }} note{{ $document->internalNoteCount() === 1 ? '' : 's' }} for your lawyer
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($document->is_published)
                                <a
                                    href="{{ route('legal.show', $document->slug) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-[8px] border border-gray-300 px-3.5 py-2 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    View live
                                </a>
                            @endif
                            <a
                                href="{{ route('super-admin.legal.edit', $document) }}"
                                class="rounded-[8px] bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                            >
                                Edit
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-super-admin-layout>
