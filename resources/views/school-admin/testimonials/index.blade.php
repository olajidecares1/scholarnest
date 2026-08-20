<x-dashboard-layout page-title="Testimonials" page-subtitle="Manage quotes from parents, students, and alumni for your public website.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">These appear in the "What People Say" section of your public website.</p>
            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Testimonial
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($testimonials as $testimonial)
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-extrabold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ Str::of($testimonial->name)->substr(0, 1)->upper() }}
                            </span>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $testimonial->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $testimonial->role ?? '—' }}</p>
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $testimonial->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $testimonial->is_active ? 'Active' : 'Hidden' }}
                        </span>
                    </div>
                    <p class="mt-3 text-xs italic leading-relaxed text-gray-600 dark:text-gray-300">&ldquo;{{ Str::limit($testimonial->quote, 140) }}&rdquo;</p>
                    <div class="mt-4 flex items-center gap-2">
                        <button
                            type="button"
                            @click="editing = @js([
                                'uuid' => $testimonial->uuid,
                                'name' => $testimonial->name,
                                'role' => $testimonial->role,
                                'quote' => $testimonial->quote,
                                'is_active' => $testimonial->is_active,
                            ]); open = true"
                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Edit
                        </button>
                        <form method="POST" action="{{ route('testimonials.destroy', $testimonial) }}" onsubmit="return confirm('Remove this testimonial?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No testimonials yet. Click "Add Testimonial" to add your first one.</p>
                </div>
            @endforelse
        </div>

        {{-- Add/Edit Testimonial modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Testimonial' : 'Add Testimonial'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('testimonials.update', ['testimonial' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('testimonials.store') }}'"
                    class="mt-4 space-y-4"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="name" label="Name" icon="M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7" x-model="editing ? editing.name : ''" required />
                        <x-text-field name="role" label="Role" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.role : ''" placeholder="e.g. Parent of JSS2 Student" helper="Optional." />
                    </div>

                    <x-textarea-field name="quote" label="Quote" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="4" x-model="editing ? editing.quote : ''" required />

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_active" value="1" x-bind:checked="editing ? editing.is_active : true" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Active (visible on your public website)
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Testimonial</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
