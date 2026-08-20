@php
    $blankForm = [
        'uuid' => '', 'name' => '', 'type' => 'student', 'orientation' => 'portrait',
        'primary_color' => '#1d4ed8', 'secondary_color' => '#111a35', 'instructions' => '',
        'show_blood_group' => false, 'show_dob' => false, 'is_default' => false,
    ];
@endphp

<x-dashboard-layout page-title="ID Card Templates" page-subtitle="Design templates used to generate student and staff ID cards.">
    <div class="space-y-6" x-data="{ open: false, side: 'front', form: @js($blankForm) }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('id-cards.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to ID Cards
            </a>
            <button
                type="button"
                @click="form = @js($blankForm); open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Template
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($templates as $template)
                <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex h-28 items-center justify-center" style="background-color: {{ $template->primary_color }};">
                        <span class="text-sm font-bold text-white">{{ $template->orientation->label() }}</span>
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $template->name }}</p>
                            <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ $template->type->label() }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $template->orientation->label() }}
                            @if ($template->is_default) &middot; Default @endif
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <button
                                type="button"
                                @click="form = @js([
                                    'uuid' => $template->uuid,
                                    'name' => $template->name,
                                    'type' => $template->type->value,
                                    'orientation' => $template->orientation->value,
                                    'primary_color' => $template->primary_color,
                                    'secondary_color' => $template->secondary_color,
                                    'instructions' => $template->instructions ?? '',
                                    'show_blood_group' => $template->show_blood_group,
                                    'show_dob' => $template->show_dob,
                                    'is_default' => $template->is_default,
                                ]); open = true"
                                class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Edit
                            </button>
                            <form method="POST" action="{{ route('id-cards.templates.destroy', $template) }}" onsubmit="return confirm('Remove {{ $template->name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No templates yet. Click "Add Template" to design your first ID card.</p>
                </div>
            @endforelse
        </div>

        {{-- Add/Edit Template modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="flex w-full max-w-3xl max-h-[90vh] flex-col gap-6 overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800 lg:flex-row">
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="form.uuid ? 'Edit Template' : 'Add Template'"></h3>
                    <form
                        method="POST"
                        :action="form.uuid ? '{{ route('id-cards.templates.update', ['template' => '__ID__']) }}'.replace('__ID__', form.uuid) : '{{ route('id-cards.templates.store') }}'"
                        enctype="multipart/form-data"
                        class="mt-4 space-y-4"
                    >
                        @csrf
                        <template x-if="form.uuid"><input type="hidden" name="_method" value="PUT"></template>

                        <x-text-field name="name" label="Template Name" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="form.name" placeholder="e.g. Standard Student Card" required />

                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Card Type</label>
                            <select name="type" x-model="form.type" class="mt-1.5 w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach (\App\Enums\IdCardHolderType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Orientation</label>
                            <select name="orientation" x-model="form.orientation" class="mt-1.5 w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                @foreach (\App\Enums\IdCardOrientation::cases() as $orientation)
                                    <option value="{{ $orientation->value }}">{{ $orientation->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Primary Color</label>
                                <input type="color" name="primary_color" x-model="form.primary_color" class="mt-1.5 h-10 w-full rounded-[8px] border border-gray-300 dark:border-gray-600">
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Secondary Color</label>
                                <input type="color" name="secondary_color" x-model="form.secondary_color" class="mt-1.5 h-10 w-full rounded-[8px] border border-gray-300 dark:border-gray-600">
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">Back-side Instructions</label>
                            <textarea
                                name="instructions"
                                x-model="form.instructions"
                                rows="4"
                                placeholder="This card is the property of {{ auth()->user()->school->name }}.&#10;It must be worn at all times on campus.&#10;It is non-transferable and must not be tampered with.&#10;Report loss or damage to the school office immediately."
                                class="mt-1.5 w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            ></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">One instruction per line. Leave blank to use the default wording shown above.</p>
                        </div>

                        <div>
                            <input
                                type="file"
                                name="background"
                                accept=".jpg,.jpeg,.png,.webp"
                                @change="form.background_preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                                class="w-full rounded-[8px] border border-gray-300 bg-white py-2.5 px-3 text-sm text-gray-700 shadow-sm file:mr-3 file:rounded-[8px] file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:file:bg-blue-900/30 dark:file:text-blue-400"
                            >
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Background image (optional). Leave blank to keep the existing one when editing.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="show_blood_group" value="1" x-model="form.show_blood_group" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                Show blood group
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="show_dob" value="1" x-model="form.show_dob" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                Show date of birth
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="is_default" value="1" x-model="form.is_default" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                Set as default for this card type
                            </label>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Template</button>
                        </div>
                    </form>
                </div>

                {{-- Live preview --}}
                <div class="flex shrink-0 flex-col items-center gap-3 border-t border-gray-100 pt-6 lg:w-56 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0 dark:border-gray-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Live Preview</p>
                    <div class="inline-flex rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                        <button type="button" @click="side = 'front'" :class="side === 'front' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-3 py-1 text-xs font-semibold transition-colors duration-200">Front</button>
                        <button type="button" @click="side = 'back'" :class="side === 'back' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'" class="rounded-[6px] px-3 py-1 text-xs font-semibold transition-colors duration-200">Back</button>
                    </div>

                    <div x-show="side === 'front'" class="overflow-hidden rounded-[6px] border border-gray-300 bg-white text-gray-900 shadow-sm" :style="`width: ${form.orientation === 'landscape' ? '85.6mm' : '53.98mm'}; height: ${form.orientation === 'landscape' ? '53.98mm' : '85.6mm'}; ${form.background_preview ? 'background-image: url(' + form.background_preview + '); background-size: cover; background-position: center;' : ''}`">
                        <div class="flex h-full flex-col" style="font-family: 'Inter', sans-serif;">
                            <div class="shrink-0 px-2.5 py-1.5" :style="`background-image: linear-gradient(135deg, ${form.secondary_color}, ${form.primary_color})`">
                                <div class="flex items-center gap-1.5">
                                    @if (auth()->user()->school->logoUrl())
                                        <img src="{{ auth()->user()->school->logoUrl() }}" class="h-6 w-6 shrink-0 rounded-full border border-white/60 object-cover">
                                    @endif
                                    <p class="truncate text-[8px] font-extrabold uppercase leading-tight text-white">{{ auth()->user()->school->name }}</p>
                                </div>
                            </div>

                            <div class="flex flex-1 gap-2 p-2" :class="form.orientation === 'landscape' ? 'flex-row items-center' : 'flex-col items-center text-center'">
                                <div class="flex shrink-0 flex-col items-center gap-1">
                                    <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-[4px] border text-sm font-bold text-gray-400" :style="`border-color: ${form.primary_color}`">JD</span>
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[4px] bg-gray-100 text-[4.5px] font-semibold text-gray-400">QR</span>
                                </div>

                                <div class="min-w-0 flex-1 space-y-1" :class="form.orientation === 'landscape' ? '' : 'w-full'">
                                    <p class="truncate text-[9px] leading-tight">
                                        <span class="font-extrabold" :style="`color: ${form.secondary_color}`">Jane</span>
                                        <span class="font-medium text-gray-500">Doe</span>
                                    </p>
                                    <div>
                                        <p class="text-[5px] font-bold uppercase tracking-wide text-gray-400" x-text="form.type === 'student' ? 'Class' : 'Department'"></p>
                                        <p class="truncate text-[7px] font-semibold text-gray-700" x-text="form.type === 'student' ? 'JSS 1' : 'Administration'"></p>
                                    </div>
                                    <div>
                                        <p class="text-[5px] font-bold uppercase tracking-wide text-gray-400" x-text="form.type === 'student' ? 'Adm No.' : 'Staff No.'"></p>
                                        <p class="truncate text-[7px] font-semibold text-gray-700">SAMPLE-0001</p>
                                    </div>
                                    <p class="truncate text-[6px] text-gray-500" x-show="form.show_blood_group">Blood Group: O+</p>
                                    <div class="flex gap-3" :class="form.orientation === 'landscape' ? '' : 'justify-center'" x-show="form.show_dob">
                                        <div>
                                            <p class="text-[5px] font-bold uppercase tracking-wide text-gray-400">DOB</p>
                                            <p class="truncate text-[6.5px] font-semibold text-gray-700">01-01-2010</p>
                                        </div>
                                        <div>
                                            <p class="text-[5px] font-bold uppercase tracking-wide text-gray-400">Expires</p>
                                            <p class="truncate text-[6.5px] font-semibold text-gray-700">31-07-2026</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center justify-between px-2.5 py-1" :style="`background-image: linear-gradient(135deg, ${form.secondary_color}, ${form.primary_color})`">
                                <p class="truncate text-[5.5px] font-semibold text-white/80">SAMPLE-CARD-000001</p>
                            </div>
                        </div>
                    </div>

                    <div x-show="side === 'back'" style="display: none;" class="overflow-hidden rounded-[6px] border border-gray-300 bg-white text-gray-900 shadow-sm" :style="`width: ${form.orientation === 'landscape' ? '85.6mm' : '53.98mm'}; height: ${form.orientation === 'landscape' ? '53.98mm' : '85.6mm'};`">
                        <div class="flex h-full flex-col" style="font-family: 'Inter', sans-serif;">
                            <div class="shrink-0 px-2.5 py-1.5 text-center" :style="`background-image: linear-gradient(135deg, ${form.secondary_color}, ${form.primary_color})`">
                                <p class="text-[5.5px] font-semibold leading-tight text-white/80">If found, please return to</p>
                                <p class="truncate text-[7px] font-extrabold uppercase leading-tight text-white">{{ auth()->user()->school->name }}</p>
                            </div>
                            <div class="flex flex-1 flex-col items-center justify-center gap-2 p-2 text-center">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[4px] bg-gray-100 text-[5px] font-semibold text-gray-400">QR</span>
                                <div class="flex w-full items-end justify-between gap-2 border-t pt-1.5" :style="`border-color: ${form.primary_color}`">
                                    <div class="flex-1 text-left">
                                        <div class="border-b border-dashed border-gray-400" style="height: 10px;"></div>
                                        <p class="mt-0.5 text-[5px] text-gray-400">Authorized Signature</p>
                                    </div>
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[3px] border border-dashed border-gray-300 text-center text-[4.5px] text-gray-400">Stamp</div>
                                </div>
                            </div>
                            <div class="shrink-0 px-2.5 py-1" :style="`background-image: linear-gradient(135deg, ${form.secondary_color}, ${form.primary_color})`">
                                <p class="truncate text-center text-[5px] text-white/70">SAMPLE-CARD-000001</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
