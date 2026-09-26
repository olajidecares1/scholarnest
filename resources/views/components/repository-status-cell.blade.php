{{-- One pupil's standing in the Result Repository, and the button that changes
     it.

     Three states worth telling apart, because they need three different things
     from whoever is reading:

       not published   nothing to do but push it
       published       nothing to do at all
       needs updating  the marks have changed since it was published, and the
                       family is still reading the old card until somebody
                       pushes again

     That third one is the reason this column exists. Without it a correction
     entered in the score grid looks done, and nobody finds out it never
     reached the parent. --}}
@props([
    'published' => null,
    'isStale' => false,
    'pushUrl',
])

<td class="whitespace-nowrap px-4 py-3">
    <div class="flex items-center gap-2">
        @if ($published === null)
            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                Not published
            </span>
        @elseif ($isStale)
            <span
                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                title="Changed since it was published on {{ $published->pushed_at->format('M j, Y g:ia') }}"
            >
                Needs updating
            </span>
        @else
            <span
                class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400"
                title="Published {{ $published->pushed_at->format('M j, Y g:ia') }} by {{ $published->pushed_by_name }}{{ $published->version > 1 ? ' (version '.$published->version.')' : '' }}"
            >
                Published
            </span>
        @endif

        <form method="POST" action="{{ $pushUrl }}">
            @csrf
            <button
                type="submit"
                title="{{ $published === null ? 'Push this result to the Repository' : 'Push this result again, replacing the published version' }}"
                class="btn inline-flex h-8 items-center justify-center gap-1.5 rounded-[8px] border border-gray-300 px-2.5 text-[11.5px] font-bold text-gray-600 transition hover:border-primary-400 hover:bg-primary-50 hover:text-primary-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-primary-900/20"
            >
                <i class="fa-solid fa-cloud-arrow-up text-[11px]"></i>
                {{ $published === null ? 'Push' : 'Re-push' }}
            </button>
        </form>
    </div>
</td>
