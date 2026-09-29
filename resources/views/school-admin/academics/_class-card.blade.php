<div class="group relative" x-data="{ editing: false }">
    <template x-if="!editing">
        <div>
            <a
                href="{{ route('students.index', ['class' => $class->name]) }}"
                class="flex h-full min-h-[7rem] flex-col items-center justify-center gap-2 rounded-[10px] border border-gray-200 bg-white p-4 text-center shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-700"
            >
                <span class="flex h-10 w-10 items-center justify-center rounded-[8px] bg-blue-50 text-blue-600 transition-colors duration-200 group-hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 5.7c2.3-1.1 5.4-1.1 8 0M12 5.7c2.6-1.1 5.7-1.1 8 0v12.6c-2.3-1.1-5.4-1.1-8 0M12 5.7v12.6M4 5.7v12.6c2.3-1.1 5.4-1.1 8 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $class->name }}</span>
            </a>
            <a href="{{ route('class-subjects.index', ['class' => $class->name]) }}" class="mt-1 block text-center text-[11px] font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">Subjects</a>
            <div class="icon-btn-group absolute right-1.5 top-1.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100 focus-within:opacity-100">
                <button
                    type="button"
                    @click="editing = true"
                    class="icon-btn icon-btn--edit icon-btn--sm"
                    data-tooltip="Rename class"
                    aria-label="Rename class"
                >
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                </button>
                <form method="POST" action="{{ route('academics.classes.destroy', $class) }}" onsubmit="return confirm('Delete {{ $class->name }}?');">
                    @csrf @method('DELETE')
                    <button
                        type="submit"
                        class="icon-btn icon-btn--delete icon-btn--sm"
                        data-tooltip="Delete class"
                        aria-label="Delete class"
                    >
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>
    </template>
    <template x-if="editing">
        <form
            method="POST"
            action="{{ route('academics.classes.update', $class) }}"
            @click.outside="editing = false"
            class="flex h-full min-h-[7rem] flex-col items-center justify-center gap-2 rounded-[10px] border border-blue-300 bg-white p-4 shadow-sm dark:border-blue-700 dark:bg-gray-800"
        >
            @csrf @method('PUT')
            <input type="hidden" name="stream" value="{{ $class->stream?->value }}">
            <input
                type="text"
                name="name"
                value="{{ $class->name }}"
                autofocus
                class="w-full text-center"
            >
            <button type="submit" class="btn rounded-[6px] bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save</button>
        </form>
    </template>
</div>
