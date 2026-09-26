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
            <i class="fa-solid fa-plus text-[17px] leading-none" aria-hidden="true"></i>
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
            <input type="text" name="name" placeholder="e.g. JSS 1" required autofocus class="w-full">
            <select name="stream" class="w-full">
                <option value="" @selected(! $stream)>No stream</option>
                @foreach (\App\Enums\ClassStream::cases() as $option)
                    <option value="{{ $option->value }}" @selected($stream === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn rounded-[6px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Add</button>
        </form>
    </template>
</div>
