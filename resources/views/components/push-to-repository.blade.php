{{-- The "Push to Repository" action, with the explanation the brief asks for.

     One component for both pages. A Class Teacher and a School Admin are doing
     exactly the same thing from two different portals, and a button that meant
     something slightly different depending on who pressed it would be a
     problem worth avoiding before it exists.

     Plain form posts rather than the fetch-and-toast pattern the rest of this
     page uses. Publishing a class's results is not a preview: it is worth a
     full page response that says what happened, and worth working when the
     JavaScript on the page does not. --}}
@props([
    'pushClassUrl',
    'className',
    'session',
    'term',
    'publishedCount' => 0,
    'totalCount' => 0,
    'staleCount' => 0,
])

<div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Result Repository</h2>

            {{-- The explanation, in the words the brief asked for: what the
                 button does, and what happens as a result. --}}
            <p class="mt-1.5 max-w-2xl text-[12.5px] leading-[1.7] text-gray-500 dark:text-gray-400">
                <span class="font-semibold text-gray-700 dark:text-gray-300">Push to Repository</span>
                saves the completed result to your school's Result Repository. Once a result has been pushed,
                it becomes available for students and pupils to check through the school's result-checking link.
            </p>

            @if ($totalCount > 0)
                <p class="mt-3 text-[12px] font-semibold text-gray-600 dark:text-gray-300">
                    <i class="fa-solid fa-database mr-1 text-[11px] text-gray-400"></i>
                    {{ $publishedCount }} of {{ $totalCount }} {{ Str::plural('result', $totalCount) }}
                    published for {{ $className }} &mdash; {{ $term->label() }}, {{ $session }}.

                    @if ($staleCount > 0)
                        <span class="ml-1 text-amber-600 dark:text-amber-400">
                            {{ $staleCount }} {{ Str::plural('has', $staleCount) === 'has' ? 'has' : 'have' }}
                            been changed since publishing and {{ $staleCount === 1 ? 'needs' : 'need' }} pushing again.
                        </span>
                    @endif
                </p>
            @endif
        </div>

        <form method="POST" action="{{ $pushClassUrl }}" class="shrink-0">
            @csrf
            <button
                type="submit"
                class="flex h-[38px] items-center justify-center gap-2 rounded-[8px] bg-primary-600 px-4 text-[13px] font-bold text-white transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            >
                <i class="fa-solid fa-cloud-arrow-up text-[12px]"></i>
                Push {{ $className }} to Repository
            </button>
        </form>
    </div>
</div>
