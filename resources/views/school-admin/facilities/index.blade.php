<x-dashboard-layout page-title="Facilities" page-subtitle="Showcase your school's facilities on your public website.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <small class="block text-sm text-gray-500 dark:text-gray-400">These appear in the "Our Facilities" section of your public website.</small>
            <button
                type="button"
                @click="editing = null; open = true"
                class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                Add Facility
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($facilities as $facility)
                <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="h-36 bg-gray-100 dark:bg-gray-700">
                        @if ($facility->imageUrl())
                            <img src="{{ $facility->imageUrl() }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600">
                                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20V10.5L12 4l8 6.5V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $facility->name }}</p>
                            @if ($facility->category)
                                <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ $facility->category }}</span>
                            @endif
                        </div>
                        @if ($facility->description)
                            <small class="field-hint mt-1">{{ Str::limit($facility->description, 80) }}</small>
                        @endif
                        <div class="mt-3 flex items-center gap-2">
                            <button
                                type="button"
                                @click="editing = @js([
                                    'uuid' => $facility->uuid,
                                    'name' => $facility->name,
                                    'category' => $facility->category,
                                    'description' => $facility->description,
                                ]); open = true"
                                class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Edit
                            </button>
                            <form method="POST" action="{{ route('facilities.destroy', $facility) }}" onsubmit="return confirm('Remove {{ $facility->name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <small class="block text-sm text-gray-500 dark:text-gray-400">No facilities yet. Click "Add Facility" to showcase your school's spaces.</small>
                </div>
            @endforelse
        </div>

        {{-- Add/Edit Facility modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Facility' : 'Add Facility'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('facilities.update', ['facility' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('facilities.store') }}'"
                    enctype="multipart/form-data"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="name" label="Name" icon="M4 20V10.5L12 4l8 6.5V20 M9 20v-6h6v6" x-model="editing ? editing.name : ''" placeholder="e.g. Science Laboratory" required />
                    <x-text-field name="category" label="Category" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.category : ''" placeholder="e.g. Academic, Sports, Recreation" helper="Optional." />
                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="3" x-model="editing ? editing.description : ''" helper="Optional." />

                    <div>
                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="w-full">
                        <small class="field-hint mt-1">Photo (optional). Leave blank to keep the existing one when editing.</small>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Facility</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
