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
            <div class="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                <button
                    type="button"
                    @click="editing = true"
                    class="rounded-full bg-white/90 p-1 text-gray-400 shadow-sm transition-colors duration-150 hover:bg-blue-50 hover:text-blue-600 dark:bg-gray-800/90 dark:text-gray-500 dark:hover:bg-blue-900/20 dark:hover:text-blue-400"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20l4.3-.7L19 8.6a1.5 1.5 0 000-2.1l-1.5-1.5a1.5 1.5 0 00-2.1 0L4.7 15.7 4 20z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
                <form method="POST" action="{{ route('academics.classes.destroy', $class) }}" onsubmit="return confirm('Delete {{ $class->name }}?');">
                    @csrf @method('DELETE')
                    <button
                        type="submit"
                        class="rounded-full bg-white/90 p-1 text-gray-400 shadow-sm transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:bg-gray-800/90 dark:text-gray-500 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
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
            <button type="submit" class="rounded-[6px] bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
        </form>
    </template>
</div>
