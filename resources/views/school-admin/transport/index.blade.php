<x-dashboard-layout page-title="Transport" page-subtitle="Manage your school's vehicle fleet.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1">
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Fleet</span>
                <a href="{{ route('transport.routes.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50">Routes</a>
            </div>

            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Vehicle
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Vehicle</th>
                            <th class="px-6 py-3 font-semibold">Plate Number</th>
                            <th class="px-6 py-3 font-semibold">Capacity</th>
                            <th class="px-6 py-3 font-semibold">Driver</th>
                            <th class="px-6 py-3 font-semibold">Routes</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($vehicles as $vehicle)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-6 py-3 font-semibold text-gray-900">{{ $vehicle->name }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $vehicle->plate_number ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $vehicle->capacity }}</td>
                                <td class="px-6 py-3 text-gray-600">
                                    {{ $vehicle->driver_name ?? '—' }}
                                    @if ($vehicle->driver_phone) <span class="text-gray-400">&middot; {{ $vehicle->driver_phone }}</span> @endif
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $vehicle->routes_count }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $vehicle->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $vehicle->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $vehicle->uuid,
                                                'name' => $vehicle->name,
                                                'plate_number' => $vehicle->plate_number,
                                                'capacity' => $vehicle->capacity,
                                                'driver_name' => $vehicle->driver_name,
                                                'driver_phone' => $vehicle->driver_phone,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('transport.destroy', $vehicle) }}" onsubmit="return confirm('Remove {{ $vehicle->name }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No vehicles yet. Click "Add Vehicle" to start your fleet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add/Edit Vehicle modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6">
                <h3 class="text-sm font-bold text-gray-900" x-text="editing ? 'Edit Vehicle' : 'Add Vehicle'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('transport.update', ['vehicle' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('transport.store') }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="name" label="Vehicle Name" icon="M4 13.5V8.5a1 1 0 011-1h1.5l1.5-3h8l1.5 3H19a1 1 0 011 1v5" x-model="editing ? editing.name : ''" placeholder="e.g. Bus 1" required />
                        <x-text-field name="plate_number" label="Plate Number" icon="M4 13.5V8.5a1 1 0 011-1h1.5l1.5-3h8l1.5 3H19a1 1 0 011 1v5" x-model="editing ? editing.plate_number : ''" helper="Optional." />
                    </div>

                    <x-text-field name="capacity" label="Seating Capacity" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="1" max="200" value="14" x-model="editing ? editing.capacity : 14" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="driver_name" label="Driver Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.driver_name : ''" helper="Optional." />
                        <x-text-field name="driver_phone" label="Driver Phone" type="tel" icon="M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z" x-model="editing ? editing.driver_phone : ''" helper="Optional." />
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
