@php
    $genderCollection = collect($genderOptions)->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all();
@endphp

<x-dashboard-layout page-title="Hostel" page-subtitle="Manage boarding houses and rooms.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Hostel
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Hostel</th>
                            <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 font-semibold">Warden</th>
                            <th class="px-6 py-3 font-semibold">Rooms</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($hostels as $hostel)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $hostel->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $hostel->gender->label() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">
                                    {{ $hostel->warden_name ?? '—' }}
                                    @if ($hostel->warden_phone) <span class="text-gray-400 dark:text-gray-500">&middot; {{ $hostel->warden_phone }}</span> @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $hostel->rooms_count }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('hostels.rooms', $hostel) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Rooms</a>
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $hostel->uuid,
                                                'name' => $hostel->name,
                                                'gender' => $hostel->gender->value,
                                                'warden_name' => $hostel->warden_name,
                                                'warden_phone' => $hostel->warden_phone,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('hostels.destroy', $hostel) }}" onsubmit="return confirm('Remove {{ $hostel->name }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No hostels yet. Click "Add Hostel" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add/Edit Hostel modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Hostel' : 'Add Hostel'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('hostels.update', ['hostel' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('hostels.store') }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="name" label="Hostel Name" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.name : ''" placeholder="e.g. Unity House" required />
                        <x-select-field name="gender" label="Type" required model="editing ? editing.gender : 'mixed'" :options="$genderCollection" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="warden_name" label="Warden Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.warden_name : ''" helper="Optional." />
                        <x-text-field name="warden_phone" label="Warden Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.warden_phone : ''" helper="Optional." />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Hostel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
