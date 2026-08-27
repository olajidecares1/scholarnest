{{-- Find a person and link them, from either side of the relationship.

     One component for both directions: a child being linked to a parent, and a
     parent being linked to a child. They are the same job seen from two pages,
     and two pickers would eventually behave differently in ways nobody
     intended.

     A search rather than a dropdown because a dropdown of every pupil in the
     school stops being usable at about the third class - and an unusable
     picker is how the wrong child gets linked to the wrong parent.

     The list comes from a school-scoped endpoint. The filtering here is a
     convenience; the rule that a school only ever sees its own people lives on
     the server. --}}
@props([
    'searchUrl',
    'submitUrl',
    'field',
    'label',
    'placeholder' => 'Search by name...',
    'classOptions' => [],
    'emptyText' => 'No matches.',
])

<div
    x-data="{
        query: '',
        className: '',
        results: [],
        selected: null,
        loading: false,
        searched: false,
        timer: null,

        // Debounced, because a request per keystroke is a request per
        // keystroke for the school's database too.
        schedule() {
            this.selected = null;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 250);
        },

        async search() {
            this.loading = true;

            try {
                const url = new URL('{{ $searchUrl }}', window.location.origin);
                url.searchParams.set('q', this.query);

                if (this.className) {
                    url.searchParams.set('class', this.className);
                }

                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await response.json();

                this.results = data.students ?? data.guardians ?? [];
            } catch (e) {
                this.results = [];
            } finally {
                this.loading = false;
                this.searched = true;
            }
        },

        choose(row) {
            this.selected = row;
            this.results = [];
            this.query = row.name;
        },
    }"
    x-init="search()"
    class="mt-6 border-t border-gray-100 pt-6 dark:border-gray-700"
>
    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $label }}</p>

    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        @if (! empty($classOptions))
            <div>
                <label class="field-label">Class</label>
                <select x-model="className" @change="search()" class="mt-1 w-full">
                    <option value="">All classes</option>
                    @foreach ($classOptions as $className)
                        <option value="{{ $className }}">{{ $className }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="{{ empty($classOptions) ? 'sm:col-span-2' : '' }}">
            <label class="field-label">Search</label>
            <input
                type="text"
                x-model="query"
                @input="schedule()"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
                class="mt-1 w-full"
            >
        </div>

        <div>
            <label class="field-label">Relationship</label>
            <input form="{{ $field }}-link-form" type="text" name="relationship" placeholder="e.g. Mother" class="mt-1 w-full">
        </div>
    </div>

    {{-- The results, and the three things that are not results: still
         loading, searched and found nothing, and not searched yet. --}}
    <div class="mt-3">
        <p x-show="loading" class="text-xs text-gray-500">Searching...</p>

        <ul x-show="!loading && results.length" class="divide-y divide-gray-100 rounded-[8px] border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
            <template x-for="row in results" :key="row.uuid">
                <li>
                    <button
                        type="button"
                        @click="choose(row)"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-primary-50 dark:hover:bg-primary-900/20"
                    >
                        <span class="font-semibold text-gray-900 dark:text-white" x-text="row.name"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="row.admission_number || row.guardian_number || row.email"></span>
                            <template x-if="row.class_name">
                                <span> &middot; <span x-text="row.class_name"></span></span>
                            </template>
                            <template x-if="row.children !== undefined">
                                <span> &middot; <span x-text="row.children"></span> linked</span>
                            </template>
                        </span>
                    </button>
                </li>
            </template>
        </ul>

        <p x-show="!loading && searched && !results.length && !selected" class="text-xs text-gray-500 dark:text-gray-400">
            {{ $emptyText }}
        </p>
    </div>

    {{-- Only ever submits a uuid the person actually picked from the list.
         Typing into the search box selects nothing on its own. --}}
    <form id="{{ $field }}-link-form" method="POST" action="{{ $submitUrl }}" class="mt-4" x-show="selected" x-cloak>
        @csrf
        <input type="hidden" name="{{ $field }}" :value="selected?.uuid">

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="submit"
                class="rounded-[8px] bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
            >
                Link <span x-text="selected?.name"></span>
            </button>

            <button type="button" @click="selected = null; query = ''; search()" class="text-xs font-semibold text-gray-500 hover:text-gray-700">
                Choose someone else
            </button>
        </div>
    </form>
</div>
