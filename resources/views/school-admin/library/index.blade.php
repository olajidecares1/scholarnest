<x-dashboard-layout page-title="Library" page-subtitle="Manage your school's book catalog.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Catalog</span>
                <a href="{{ route('library.loans.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Loans</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600">Titles</p>
                    <x-stat-tooltip text="Number of distinct book titles in your catalog." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalTitles) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-blue-600">Total Copies</p>
                    <x-stat-tooltip text="Combined physical copies across every title, whether on the shelf or on loan." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalCopies) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-orange-600">Copies on Loan</p>
                    <x-stat-tooltip text="Copies currently checked out and not yet returned." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($onLoanCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search title, author, ISBN..."
                        class="h-11 w-64 rounded-[8px] border border-gray-300 px-3 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-[3px] focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                    <div class="w-40">
                        <x-select-field name="category" placeholder="All Categories" :selected="request('category')" :options="['' => 'All Categories'] + $categories->mapWithKeys(fn ($c) => [$c => $c])->all()" />
                    </div>
                    <button type="submit" class="h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Search</button>
                    @if (request('search') || request('category'))
                        <a href="{{ route('library.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>

                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Book
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold">Author</th>
                            <th class="px-6 py-3 font-semibold">Category</th>
                            <th class="px-6 py-3 font-semibold">Available</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($books as $book)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $book->title }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $book->author ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $book->category ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $book->copies_available > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                        {{ $book->copies_available }} / {{ $book->copies_total }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $book->uuid,
                                                'title' => $book->title,
                                                'author' => $book->author,
                                                'isbn' => $book->isbn,
                                                'category' => $book->category,
                                                'copies_total' => $book->copies_total,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('library.destroy', $book) }}" onsubmit="return confirm('Remove {{ $book->title }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No books yet. Click "Add Book" to start your catalog.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($books->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $books->links() }}
                </div>
            @endif
        </div>

        {{-- Add/Edit Book modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Book' : 'Add Book'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('library.update', ['book' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('library.store') }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="title" label="Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.title : ''" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="author" label="Author" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.author : ''" helper="Optional." />
                        <x-text-field name="category" label="Category" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.category : ''" helper="Optional." />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="isbn" label="ISBN" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.isbn : ''" helper="Optional." />
                        <x-text-field name="copies_total" label="Total Copies" type="number" icon="M8 12.3l2.6 2.6L16.3 9" value="1" min="1" max="1000" x-model="editing ? editing.copies_total : 1" required />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
