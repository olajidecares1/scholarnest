@php
    $presetColors = collect($presets)->mapWithKeys(fn ($preset, $key) => [$key => $preset['colors']['500']]);
@endphp

<x-super-admin-layout page-title="Themes" page-subtitle="Customize the platform's color theme, logo, and favicon.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div
            class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
            x-data="{ selected: '{{ $settings->theme_preset }}', colors: @js($presetColors) }"
        >
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Color Theme</h2>
            <p class="field-hint mt-1">Pick a palette. Preview updates instantly; save to apply platform-wide.</p>

            <form method="POST" action="{{ route('super-admin.themes.update') }}" class="mt-4 space-y-2">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($presets as $key => $preset)
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-[8px] border-2 p-3 transition-all duration-200"
                            :class="selected === '{{ $key }}' ? 'border-primary-500 shadow-sm' : 'border-gray-200 dark:border-gray-700'"
                        >
                            <input type="radio" name="theme_preset" value="{{ $key }}" x-model="selected" class="sr-only">
                            <span class="h-8 w-8 shrink-0 rounded-full shadow-inner" style="background-color: {{ $preset['colors']['500'] }};"></span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $preset['label'] }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="rounded-[5px] border border-dashed border-gray-300 p-4 dark:border-gray-600 lg:rounded-[10px]">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Live Preview</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full text-white shadow-sm"
                            :style="{ backgroundColor: colors[selected] }"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <button
                            type="button"
                            class="rounded-[8px] px-4 py-2 text-sm font-bold text-white shadow-md"
                            :style="{ backgroundColor: colors[selected] }"
                        >
                            Sample Button
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="flex items-center justify-center gap-2 rounded-[8px] bg-primary-500 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-lg"
                >
                    Save Theme
                </button>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Logo</h2>
            <p class="field-hint mt-1">PNG, JPG, or SVG. Max 2MB.</p>

            <div class="mt-4 flex items-center gap-4">
                <span class="flex h-16 w-16 items-center justify-center rounded-[5px] border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900 lg:rounded-[10px]">
                    <img
                        src="{{ $settings->logoUrl('images/logo-mark.png') }}"
                        alt="Current logo"
                        class="h-full w-full object-contain"
                    >
                </span>

                <form method="POST" action="{{ route('super-admin.themes.logo.update') }}" enctype="multipart/form-data" class="flex flex-1 items-center gap-3">
                    @csrf
                    <input
                        type="file"
                        name="logo"
                        accept=".png,.jpg,.jpeg,.svg"
                        required
                        class="flex-1"
                    >
                    <button type="submit" class="rounded-[8px] bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600">Upload</button>
                </form>
            </div>

            <x-input-error :messages="$errors->get('logo')" class="mt-3" />
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Favicon</h2>
            <p class="field-hint mt-1">PNG or ICO. Max 2MB.</p>

            <div class="mt-4 flex items-center gap-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-[5px] border border-gray-200 bg-gray-50 p-1.5 dark:border-gray-700 dark:bg-gray-900 lg:rounded-[10px]">
                    {{-- The same address the browser tab uses, cache-buster
                         and all, so this preview cannot show one icon while
                         the tab shows another. --}}
                    <img
                        src="{{ \App\Support\Favicon::for()->href }}"
                        alt="Current favicon"
                        class="h-full w-full object-contain"
                    >
                </span>

                <form method="POST" action="{{ route('super-admin.themes.favicon.update') }}" enctype="multipart/form-data" class="flex flex-1 items-center gap-3">
                    @csrf
                    <input
                        type="file"
                        name="favicon"
                        accept=".png,.ico"
                        required
                        class="flex-1"
                    >
                    <button type="submit" class="rounded-[8px] bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600">Upload</button>
                </form>
            </div>

            {{-- A rejected upload used to say nothing at all: the page simply
                 reloaded with the old icon still showing, which is
                 indistinguishable from the feature being broken. --}}
            <x-input-error :messages="$errors->get('favicon')" class="mt-3" />
        </div>
    </div>
</x-super-admin-layout>
