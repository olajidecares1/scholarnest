@php
    $stream ??= null;
@endphp

<div x-data="{ open: false }" class="relative">
    <template x-if="!open">
        <button
            type="button"
            @click="open = true"
            class="flex h-full min-h-[7rem] w-full flex-col items-center justify-center gap-1.5 rounded-[10px] border-2 border-dashed border-gray-300 p-4 text-gray-400 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:border-blue-400 hover:text-blue-500 dark:border-gray-600 dark:text-gray-500 dark:hover:border-blue-600"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            <span class="text-xs font-semibold">Add Class</span>
        </button>
    </template>
    <template x-if="open">
        <form
            method="POST"
            action="{{ route('academics.classes.store', $level) }}"
            @click.outside="open = false"
            class="flex h-full flex-col justify-center gap-2 rounded-[10px] border border-blue-300 bg-white p-3 shadow-sm dark:border-blue-700 dark:bg-gray-800"
        >
            @csrf
            <input type="text" name="name" placeholder="e.g. JSS 1" required autofocus class="w-full rounded-[6px] border border-gray-300 px-2 py-1.5 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <select name="stream" class="w-full rounded-[6px] border border-gray-300 px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="" @selected(! $stream)>No stream</option>
                @foreach (\App\Enums\ClassStream::cases() as $option)
                    <option value="{{ $option->value }}" @selected($stream === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-[6px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Add</button>
        </form>
    </template>
</div>
