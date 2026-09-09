{{-- The Principal's library of reusable remarks.

     A head writes the same handful of sentences several hundred times a term.
     These are the ones they have chosen to keep, and there is no limit on how
     many that is.

     Saving a sentence here does NOT change any result. What lands on a report
     card is copied onto that pupil's own record, so rewording an entry later
     never rewrites cards already issued - which is the only way a remark can
     mean what it said on the day it was written. --}}
<x-dashboard-layout page-title="Principal's Remark Library" page-subtitle="Reusable remarks you can assign to any pupil's result.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-300 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Write a new remark</h2>
            <p class="field-hint mt-0.5">
                Saved to your school&rsquo;s library and available on every pupil&rsquo;s result.
                You are your school&rsquo;s Principal on AkademicNest, so these are your remarks.
            </p>

            <form method="POST" action="{{ route('results.remark-library.store') }}" class="mt-4 space-y-3">
                @csrf

                <div>
                    <label for="body" class="field-label font-bold text-gray-900 dark:text-gray-100">Remark</label>
                    <textarea
                        id="body"
                        name="body"
                        rows="2"
                        maxlength="1000"
                        required
                        class="mt-1.5 w-full"
                        placeholder="e.g. Outstanding performance. Keep up the excellent work."
                    >{{ old('body') }}</textarea>
                    @error('body')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700">Save to Library</button>
                </div>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Saved remarks</h2>
                <p class="field-hint">{{ $remarks->count() }} saved &mdash; there is no limit.</p>
            </div>

            @forelse ($remarks as $remark)
                <div class="border-b border-gray-100 px-6 py-4 last:border-b-0 dark:border-gray-700">
                    <form method="POST" action="{{ route('results.remark-library.update', $remark) }}" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        @csrf
                        @method('PUT')

                        {{-- Shown italic here because that is how it prints on
                             a report card - what you see saved is what a
                             parent reads. --}}
                        <textarea
                            name="body"
                            rows="2"
                            maxlength="1000"
                            required
                            class="w-full flex-1 italic"
                            aria-label="Remark text"
                        >{{ $remark->body }}</textarea>

                        <div class="flex shrink-0 gap-2">
                            <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">Save</button>
                        </div>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('results.remark-library.destroy', $remark) }}"
                        class="mt-2"
                        onsubmit="return confirm('Remove this remark from your library? Results already carrying it are unchanged.')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-red-700 hover:text-red-800 dark:text-red-400">Delete</button>
                    </form>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">
                    No saved remarks yet. Write one above, or tick &ldquo;Save to my library&rdquo; when you write a
                    remark on a pupil&rsquo;s result.
                </p>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
