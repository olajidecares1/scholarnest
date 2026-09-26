<x-student-layout page-title="Library" page-subtitle="Browse the catalogue and your loan history">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="GET" class="mb-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title or author..." class="w-full">
            </form>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse ($books as $book)
                    <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $book->title }}</p>
                        <small class="field-hint">{{ $book->author }}</small>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $book->category }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $book->copies_available > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $book->copies_available > 0 ? $book->copies_available.' Available' : 'Unavailable' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <small class="block col-span-2 rounded-[10px] border border-dashed border-gray-200 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No books found.</small>
                @endforelse
            </div>

            <div class="mt-4">{{ $books->links() }}</div>
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">My Loans</h2>
            <div class="mt-4 space-y-3">
                @forelse ($myLoans as $loan)
                    <div class="rounded-[8px] bg-gray-50 p-3 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $loan->book->title }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $loan->statusBadgeClasses() }}">{{ $loan->status() }}</span>
                        </div>
                        <small class="field-hint mt-1">Due {{ $loan->due_at->format('M j, Y') }}</small>
                    </div>
                @empty
                    <small class="block text-sm text-gray-500 dark:text-gray-400">You haven't borrowed any books yet.</small>
                @endforelse
            </div>
        </div>
    </div>
</x-student-layout>
