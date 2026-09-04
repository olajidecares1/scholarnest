{{-- Editing one legal document.

     TWO TABS, ONE FORM: the markdown you type, and the page a school actually
     sees. The preview is not decoration - these documents are long, and the
     difference between a heading that renders and a heading that does not is
     invisible in a textarea. --}}
<x-super-admin-layout
    :page-title="$document->title"
    page-subtitle="Editing what every school and visitor reads at /legal/{{ $document->slug }}"
>
    <div
        class="space-y-6"
        x-data="{
            tab: 'edit',
            body: @js($document->body),
            dirty: false,
        }"
        x-init="$watch('body', () => dirty = true)"
    >
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('super-admin.legal.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                &larr; All legal documents
            </a>

            <div class="flex items-center gap-1 rounded-[8px] border border-gray-300 p-1 dark:border-gray-600">
                <button
                    type="button"
                    @click="tab = 'edit'"
                    :class="tab === 'edit' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'"
                    class="rounded-[6px] px-3.5 py-1.5 text-xs font-semibold transition-colors"
                >
                    Edit
                </button>
                <button
                    type="button"
                    @click="tab = 'preview'"
                    :class="tab === 'preview' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'"
                    class="rounded-[6px] px-3.5 py-1.5 text-xs font-semibold transition-colors"
                >
                    The page schools see
                </button>
            </div>
        </div>

        {{-- Editing --}}
        <form method="POST" action="{{ route('super-admin.legal.update', $document) }}" x-show="tab === 'edit'" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">Details</h3>
                <small class="field-hint mt-0.5">How this document is listed and identified. The web address cannot be changed &mdash; schools already have it.</small>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-text-field
                        name="title"
                        label="Title"
                        :value="$document->title"
                        required
                        helper="Shown as the page heading, in the list of documents, and on the registration form's agreement line."
                    />
                    <div>
                        <label class="field-label">Web address</label>
                        <input type="text" value="/legal/{{ $document->slug }}" disabled class="mt-1 w-full">
                        <small class="field-hint mt-1">Fixed. This link is printed on the registration form and will be in emails and bookmarks; renaming it would break all of them silently.</small>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-text-field
                        name="version"
                        label="Version"
                        :value="$document->version"
                        required
                        helper="Raise this whenever you change anything of substance. It is how you say later which version a particular school agreed to."
                    />
                    <x-text-field
                        name="effective_date"
                        label="Effective date"
                        :value="$document->effective_date"
                        placeholder="e.g. 1 October 2026"
                        helper="Optional. The date these terms started applying, if it differs from when you saved them."
                    />
                </div>

                <div class="mt-4">
                    <label class="field-label" for="summary">Summary</label>
                    <textarea id="summary" name="summary" rows="2" class="mt-1.5 w-full">{{ old('summary', $document->summary) }}</textarea>
                    <small class="field-hint mt-1">One sentence describing what this document covers. Shown on the list of documents and used as the page description search engines read.</small>
                    @error('summary')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $document->is_published)) class="w-4 text-blue-600">
                        Published
                    </label>
                    <small class="field-hint mt-1">
                        Unticked, the page returns "not found" rather than half-rendering, and it disappears from the list of documents.
                        @if (in_array($document->slug, ['terms', 'privacy', 'cookies'], true))
                            <strong class="text-amber-700 dark:text-amber-400">This one is linked from the registration form &mdash; hiding it leaves schools agreeing to a document they cannot open.</strong>
                        @endif
                    </small>
                </div>
            </div>

            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300">The document</h3>
                <small class="field-hint mt-0.5 max-w-2xl">
                    Written in Markdown. <code>## Heading</code> starts a section, <code>**bold**</code> emphasises,
                    <code>-&nbsp;item</code> makes a list, and a row of <code>|&nbsp;cells&nbsp;|</code> makes a table.
                    Anything you are unsure of, switch to &ldquo;The page schools see&rdquo; and look.
                </small>

                <textarea
                    id="body"
                    name="body"
                    x-model="body"
                    rows="30"
                    class="mt-3 w-full font-mono text-[12.5px] leading-[1.7]"
                    spellcheck="false"
                >{{ old('body', $document->body) }}</textarea>
                @error('body')<p class="field-error">{{ $message }}</p>@enderror

                {{-- The fence, explained where it is used rather than only in
                     the code. Somebody editing this document will meet the
                     markers and needs to know they are load-bearing. --}}
                <div class="mt-4 rounded-[8px] border border-purple-200 bg-purple-50 p-4 dark:border-purple-900/50 dark:bg-purple-900/20">
                    <p class="text-[12px] font-bold text-purple-900 dark:text-purple-300">Notes for your lawyer</p>
                    <p class="mt-1 text-[12px] leading-[1.7] text-purple-800 dark:text-purple-400">
                        Anything between <code>&lt;!-- internal:start --&gt;</code> and <code>&lt;!-- internal:end --&gt;</code>
                        is removed before the page is shown, so you can leave questions for a lawyer inside the document
                        itself without schools reading them. Use it for open points; do not use it to hide something a
                        school ought to know.
                        @if ($document->internalNoteCount() > 0)
                            <br><strong>This document currently has {{ $document->internalNoteCount() }} of them.</strong>
                        @endif
                    </p>
                </div>

                @if ($document->placeholderCount() > 0)
                    <div class="mt-3 rounded-[8px] border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <p class="text-[12px] font-bold text-amber-900 dark:text-amber-300">
                            {{ $document->placeholderCount() }} placeholder{{ $document->placeholderCount() === 1 ? '' : 's' }} still to fill in
                        </p>
                        <p class="mt-1 text-[12px] leading-[1.7] text-amber-800 dark:text-amber-400">
                            Search the text for <code>TO BE PROVIDED</code>. These are visible to schools exactly as
                            written &mdash; a reader sees "operated by [TO BE PROVIDED]" until you replace it with your
                            company name, address and contact details.
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-[12px] text-gray-500 dark:text-gray-400" x-show="dirty" x-cloak>
                    Unsaved changes. Schools still see the previous text until you save.
                </p>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('super-admin.legal.index') }}" class="rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                        Cancel
                    </a>
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                        Save &amp; publish
                    </button>
                </div>
            </div>
        </form>

        {{-- The page as a school sees it.

             Rendered from what is SAVED, not from the textarea - the preview is
             only honest if it shows what is actually live. Editing the text and
             switching tabs shows the previous version until you save, and the
             notice says so rather than leaving anyone guessing. --}}
        <div x-show="tab === 'preview'" x-cloak class="space-y-4">
            <div class="rounded-[10px] border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                <p class="text-[12px] leading-[1.7] text-gray-600 dark:text-gray-400">
                    This is the saved document exactly as a school reads it &mdash; notes for your lawyer removed.
                    Unsaved edits are not shown here.
                    <a href="{{ route('legal.show', $document->slug) }}" target="_blank" rel="noopener" class="font-semibold text-blue-600 hover:underline">Open the real page</a>
                </p>
            </div>

            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-8">
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-primary-600">{{ config('app.name', 'ScholarNest') }} Legal</p>
                <h2 class="mt-2 text-[26px] font-extrabold leading-tight tracking-tight text-gray-900 dark:text-white">{{ $document->title }}</h2>
                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 border-b border-gray-200 pb-5 text-[12px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <span>Version <strong class="font-semibold text-gray-700 dark:text-gray-200">{{ $document->version }}</strong></span>
                    <span>Last updated <strong class="font-semibold text-gray-700 dark:text-gray-200">{{ $document->updated_at->format('j F Y') }}</strong></span>
                </div>

                <article class="legal-prose mt-6">
                    {!! $document->renderedHtml() !!}
                </article>
            </div>
        </div>
    </div>
</x-super-admin-layout>
