<x-dashboard-layout page-title="Transport Routes" page-subtitle="Manage routes and assign vehicles.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1">
                <a href="{{ route('transport.index') }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50">Fleet</a>
                <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Routes</span>
            </div>

            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Route
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Route</th>
                            <th class="px-6 py-3 font-semibold">Vehicle</th>
                            <th class="px-6 py-3 font-semibold">Fee</th>
                            <th class="px-6 py-3 font-semibold">Students</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($routes as $route)
                            <tr class="transition-colors duration-200 hover:bg-gray-50">
                                <td class="px-6 py-3 font-semibold text-gray-900">{{ $route->name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $route->vehicle?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $route->fee ? '₦'.number_format((float) $route->fee, 2) : '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $route->assignments_count }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('transport.routes.students', $route) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50">Manage Students</a>
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $route->uuid,
                                                'name' => $route->name,
                                                'description' => $route->description,
                                                'fee' => $route->fee,
                                                'transport_vehicle_id' => $route->transport_vehicle_id,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('transport.routes.destroy', $route) }}" onsubmit="return confirm('Delete {{ $route->name }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No routes yet. Click "Add Route" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add/Edit Route modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6">
                <h3 class="text-sm font-bold text-gray-900" x-text="editing ? 'Edit Route' : 'Add Route'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('transport.routes.update', ['route' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('transport.routes.store') }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="name" label="Route Name" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" x-model="editing ? editing.name : ''" placeholder="e.g. Ikeja Route" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-select-field name="transport_vehicle_id" label="Vehicle" placeholder="Unassigned" model="editing ? editing.transport_vehicle_id : ''" :options="$vehicles->mapWithKeys(fn ($v) => [$v->id => $v->name])->all()" />
                        <x-text-field name="fee" label="Monthly Fee (₦)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0" step="0.01" x-model="editing ? editing.fee : ''" helper="Optional." />
                    </div>

                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="2" x-text="editing ? editing.description : ''" />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
