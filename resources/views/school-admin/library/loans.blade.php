<x-dashboard-layout page-title="Library Loans" page-subtitle="Issue and track book loans.">
    <div class="space-y-6" x-data="{ open: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <a href="{{ route('library.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Catalog</a>
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Loans</span>
            </div>

            <button
                type="button"
                @click="open = true"
                class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                Issue Book
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div class="flex gap-1 rounded-[8px] border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-700/50">
                    @foreach (['active' => 'Active', 'overdue' => 'Overdue', 'returned' => 'Returned', 'all' => 'All'] as $value => $label)
                        <a href="{{ route('library.loans.index', ['status' => $value]) }}" class="btn rounded-[6px] px-3 py-1.5 text-xs font-semibold transition-colors duration-150 {{ $status === $value ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search book or student..."
                        class="w-56"
                    >
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Book</th>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Borrowed</th>
                            <th class="px-6 py-3 font-semibold">Due</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($loans as $loan)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $loan->book->title }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $loan->student->fullName() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $loan->borrowed_at->format('M j, Y') }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $loan->due_at->format('M j, Y') }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $loan->statusBadgeClasses() }}">{{ $loan->status() }}</span>
                                </td>
                                <td class="px-6 py-3">
                                    @unless ($loan->isReturned())
                                        <form method="POST" action="{{ route('library.loans.return', $loan) }}">
                                            @csrf
                                            <button type="submit" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Mark Returned</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">Returned {{ $loan->returned_at->format('M j, Y') }}</span>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No {{ $status === 'all' ? '' : $status.' ' }}loans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($loans->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>

        {{-- Issue Book modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Issue Book</h3>
                <form method="POST" action="{{ route('library.loans.store') }}" class="mt-4 space-y-2">
                    @csrf
                    <x-select-field name="book_id" label="Book" required placeholder="Select an available book" :options="$books->pluck('title', 'id')->all()" />
                    <x-select-field name="student_id" label="Student" required placeholder="Select a student" :options="$students->mapWithKeys(fn ($s) => [$s->id => $s->fullName()])->all()" />
                    <x-text-field name="due_at" label="Due Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" :value="now()->addWeeks(2)->toDateString()" required />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Issue Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
