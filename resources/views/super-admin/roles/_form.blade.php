@php
    $selected = old('permissions', $role->permissions ?? []);
@endphp

<div>
    <x-input-label for="name" value="Role Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $role->name ?? '')" required autofocus placeholder="e.g. Finance Reviewer" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

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
