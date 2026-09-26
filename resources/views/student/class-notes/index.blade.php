{{-- Every class note sent to this pupil's class.

     The list is already filtered by school AND class in the controller; there
     is nothing on this page a pupil could change to see another class's work.
--}}
<x-student-layout page-title="Class Notes" page-subtitle="Notes your teachers have sent to {{ $student->class_name ?: 'your class' }}.">
    <div class="space-y-3">
        @forelse ($notes as $note)
            <a href="{{ route('student.class-notes.show', [$school, $note]) }}" class="block rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                        <i class="fa-solid fa-file-word"></i>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $note->title }}</p>
                            <span class="text-[11px] text-gray-400">{{ $note->created_at->diffForHumans() }}</span>
                        </div>

                        <small class="block mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                            @if ($note->subject){{ $note->subject }} &middot; @endif
                            @if ($note->staff){{ $note->staff->fullName() }} &middot; @endif
                            {{ $note->readableSize() }}
                        </small>

                        @if ($note->description)
                            <small class="block mt-1.5 line-clamp-2 text-[12.5px] leading-[1.6] text-gray-600 dark:text-gray-300">{{ $note->description }}</small>
                        @endif
                    </div>

                    <i class="fa-solid fa-chevron-right mt-1 shrink-0 text-xs text-gray-300"></i>
                </div>
            </a>
        @empty
            <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <i class="fa-solid fa-file-word text-2xl text-gray-300"></i>
                <small class="block mt-2 text-sm text-gray-500 dark:text-gray-400">No class notes yet.</small>
                <small class="block mt-1 text-xs text-gray-400">When a teacher sends one to your class, it will appear here.</small>
            </div>
        @endforelse

        @if ($notes->hasPages())
            <div class="pt-2">{{ $notes->links() }}</div>
        @endif
    </div>
</x-student-layout>
