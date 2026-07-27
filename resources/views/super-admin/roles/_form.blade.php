@php
    $selected = old('permissions', $role->permissions ?? []);
@endphp

<x-text-field
    id="name"
    name="name"
    label="Role Name"
    icon="M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"
    helper="A short, descriptive name for this permission set."
    :value="old('name', $role->name ?? '')"
    required
    autofocus
    placeholder="e.g. Finance Reviewer"
/>

<div>
    <x-input-label value="Permissions" />
    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Choose which sections this role can access.</p>
    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($permissions as $key => $label)
            <label class="flex items-center gap-2 rounded-[5px] border border-gray-200 px-3 py-2 text-sm text-gray-700 transition-colors duration-200 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700/50 lg:rounded-[10px]">
                <input
                    type="checkbox"
                    name="permissions[]"
                    value="{{ $key }}"
                    @checked(in_array($key, $selected, true))
                    class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                >
                {{ $label }}
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
</div>
