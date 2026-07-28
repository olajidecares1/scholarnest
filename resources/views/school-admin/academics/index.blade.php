<x-dashboard-layout page-title="Academics" page-subtitle="Organize your school into academic levels and classes.">
    <div class="space-y-6" x-data="{ addLevelOpen: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <button
                type="button"
                @click="addLevelOpen = true"
                class="flex items-center gap-2 rounded-[2px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Academic Level
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @forelse ($levels as $level)
                <div
                    class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm lg:rounded-[10px]"
                    x-data="{ addClassOpen: false, editingLevel: false, levelName: @js($level->name) }"
                >
                    <div class="flex items-center justify-between gap-2">
                        <template x-if="!editingLevel">
                            <h2 class="text-sm font-bold text-gray-900" x-text="levelName"></h2>
                        </template>
                        <form
                            x-show="editingLevel"
                            method="POST"
                            action="{{ route('academics.levels.update', $level) }}"
                            class="flex flex-1 items-center gap-2"
                        >
                            @csrf @method('PUT')
                            <input type="text" name="name" x-model="levelName" class="w-full rounded-[2px] border border-gray-300 px-2 py-1 text-sm font-bold text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                            <button type="submit" class="shrink-0 rounded-[2px] bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
                        </form>

                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" @click="editingLevel = !editingLevel" class="rounded-[2px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-blue-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20l4.3-.7L19 8.6a1.5 1.5 0 000-2.1l-1.5-1.5a1.5 1.5 0 00-2.1 0L4.7 15.7 4 20z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                            <form method="POST" action="{{ route('academics.levels.destroy', $level) }}" onsubmit="return confirm('Delete {{ $level->name }} and every class inside it?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-[2px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($level->classes as $class)
                            <span class="group inline-flex items-center gap-2 rounded-[2px] border border-gray-200 bg-gray-50 py-1.5 pl-3 pr-1.5 text-sm text-gray-700">
                                <a href="{{ route('students.index', ['class' => $class->name]) }}" class="hover:text-blue-600">{{ $class->name }}</a>
                                <form method="POST" action="{{ route('academics.classes.destroy', $class) }}" onsubmit="return confirm('Delete {{ $class->name }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-[2px] p-1 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                                    </button>
                                </form>
                            </span>
                        @empty
                            <p class="text-xs text-gray-400">No classes in this level yet.</p>
                        @endforelse
                    </div>

                    <button type="button" @click="addClassOpen = true" class="mt-4 text-xs font-semibold text-blue-600 hover:text-blue-700">+ Add Class</button>

                    <form
                        x-show="addClassOpen"
                        method="POST"
                        action="{{ route('academics.classes.store', $level) }}"
                        class="mt-3 flex items-center gap-2"
                    >
                        @csrf
                        <input type="text" name="name" placeholder="e.g. JSS 1" required class="w-full rounded-[2px] border border-gray-300 px-2 py-1.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                        <button type="submit" class="shrink-0 rounded-[2px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Add</button>
                    </form>
                </div>
            @empty
                <div class="col-span-full rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 lg:rounded-[10px]">
                    No academic levels yet. Click "Add Academic Level" to get started.
                </div>
            @endforelse
        </div>

        {{-- Add Level modal --}}
        <div x-show="addLevelOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addLevelOpen = false" class="w-full max-w-md rounded-[2px] bg-white p-6">
                <h3 class="text-sm font-bold text-gray-900">Add Academic Level</h3>
                <p class="mt-1 text-xs text-gray-500">e.g. Senior Secondary School, Junior Secondary School, Upper Primary, Lower Primary, Nursery, Kindergarten/Creche, or any other level your school uses.</p>
                <form method="POST" action="{{ route('academics.levels.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="text" name="name" required placeholder="Level name" class="w-full rounded-[2px] border border-gray-300 px-3 py-2.5 text-sm font-medium text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addLevelOpen = false" class="rounded-[2px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button type="submit" class="rounded-[2px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
