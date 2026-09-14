@php
    // A new template opens on the default card, the same colours and wording
    // an untouched school already gets, so "edit this into our card" starts
    // from what the school is looking at rather than from a blank form.
    $defaults = \App\Support\IdCardDesign::newTemplateDefaults($school);

    $blankForm = [
        'uuid' => '', 'name' => '', 'type' => 'student', 'orientation' => 'portrait',
        'primary_color' => $defaults['primary_color'],
        'secondary_color' => $defaults['secondary_color'],
        'accent_color' => $defaults['accent_color'],
        'instructions' => $defaults['instructions'],
        'show_blood_group' => false, 'show_dob' => false, 'is_default' => false,
    ];
@endphp

<x-dashboard-layout page-title="ID Card Templates" page-subtitle="Design templates used to generate student and staff ID cards.">
    <div
        class="space-y-6"
        x-data="{
            open: false,
            side: 'front',
            form: @js($blankForm),

            sampleFront: '',
            sampleBack: '',
            sampleLoading: false,
            sampleTimer: null,

            /*
             * Ask the server to draw the specimen again.
             *
             * Debounced, because a colour input fires on every step as it is
             * dragged, and each of those would otherwise be a request.
             */
            refreshSample() {
                window.clearTimeout(this.sampleTimer);

                this.sampleTimer = window.setTimeout(async () => {
                    this.sampleLoading = true;

                    const query = new URLSearchParams({
                        type: this.form.type ?? 'student',
                        orientation: this.form.orientation ?? 'portrait',
                        primary_color: this.form.primary_color ?? '',
                        secondary_color: this.form.secondary_color ?? '',
                        accent_color: this.form.accent_color ?? '',
                        instructions: this.form.instructions ?? '',
                        show_blood_group: this.form.show_blood_group ? 1 : 0,
                    });

                    try {
                        const response = await fetch('{{ route('id-cards.templates.sample') }}?' + query, {
                            headers: { Accept: 'application/json' },
                        });

                        if (! response.ok) {
                            throw new Error(String(response.status));
                        }

                        const card = await response.json();

                        this.sampleFront = card.front;
                        this.sampleBack = card.back;
                    } catch {
                        /*
                         * Leave the last good specimen on screen rather than
                         * blanking it. A dropped request is not a reason to
                         * show a School Admin an empty box.
                         */
                    } finally {
                        this.sampleLoading = false;
                    }
                }, 250);
            },
        }"
        x-init="
            refreshSample();
            $watch('form.type', () => refreshSample());
            $watch('form.orientation', () => refreshSample());
            $watch('form.primary_color', () => refreshSample());
            $watch('form.secondary_color', () => refreshSample());
            $watch('form.accent_color', () => refreshSample());
            $watch('form.instructions', () => refreshSample());
            $watch('form.show_blood_group', () => refreshSample());
        "
    >
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
                        <p class="field-hint mt-1">
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
                                    // A template saved before this colour existed has none,
                                    // so the editor opens on the reference red rather than
                                    // on black, which is what an empty colour input shows.
                                    'accent_color' => $template->accent_color ?: \App\Support\IdCardDesign::ACCENT,
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
                        class="mt-4 space-y-2"
                    >
                        @csrf
                        <template x-if="form.uuid"><input type="hidden" name="_method" value="PUT"></template>

                        <x-text-field name="name" label="Template Name" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="form.name" placeholder="e.g. Standard Student Card" required />

                        <div>
                            <label class="field-label">Card Type</label>
                            <select name="type" x-model="form.type" class="mt-1.5 w-full">
                                @foreach (\App\Enums\IdCardHolderType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="field-label">Orientation</label>
                            <select name="orientation" x-model="form.orientation" class="mt-1.5 w-full">
                                @foreach (\App\Enums\IdCardOrientation::cases() as $orientation)
                                    <option value="{{ $orientation->value }}">{{ $orientation->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="field-label">Primary Colour</label>
                                <input type="color" name="primary_color" x-model="form.primary_color" class="mt-1.5 w-full">
                                <p class="field-hint">Staff badges.</p>
                            </div>
                            <div>
                                <label class="field-label">Secondary Colour</label>
                                <input type="color" name="secondary_color" x-model="form.secondary_color" class="mt-1.5 w-full">
                                <p class="field-hint">Masthead, footer, back panel.</p>
                            </div>
                            <div>
                                <label class="field-label">Accent Colour</label>
                                <input type="color" name="accent_color" x-model="form.accent_color" class="mt-1.5 w-full">
                                <p class="field-hint">Stripe, tagline, student badge.</p>
                            </div>
                        </div>

                        <div>
                            <label class="field-label">Back-side Instructions</label>
                            <textarea
                                name="instructions"
                                x-model="form.instructions"
                                rows="4"
                                placeholder="This card is the property of {{ auth()->user()->school->name }}.&#10;It must be worn at all times on campus.&#10;It is non-transferable and must not be tampered with.&#10;Report loss or damage to the school office immediately."
                                class="mt-1.5 w-full"
                            ></textarea>
                            <p class="field-hint mt-1">One instruction per line. Leave blank to use the default wording shown above.</p>
                        </div>

                        <div>
                            <input
                                type="file"
                                name="background"
                                accept=".jpg,.jpeg,.png,.webp"
                                @change="form.background_preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                                class="w-full"
                            >
                            <p class="field-hint mt-1">Background image (optional). Leave blank to keep the existing one when editing.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="show_blood_group" value="1" x-model="form.show_blood_group" class="text-blue-600">
                                Show blood group
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="show_dob" value="1" x-model="form.show_dob" class="text-blue-600">
                                Show date of birth
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="is_default" value="1" x-model="form.is_default" class="text-blue-600">
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

                    {{-- The specimen is the REAL card, rendered by the real
                         templates and fetched again whenever a colour or the
                         card type changes.

                         It used to be a miniature drawn by hand here, a
                         gradient header, a small photo box, an "Authorized
                         Signature" line. None of it was the card. It was
                         written once, the card moved on, and what a School
                         Admin approved stopped resembling what printed. --}}
                    <div class="relative" style="width: 53.98mm; min-height: 85.6mm;">
                        <div x-show="sampleLoading" class="absolute inset-0 z-10 flex items-center justify-center rounded-[6px] bg-white/70 dark:bg-gray-900/70" style="display: none;">
                            <i class="fa-solid fa-circle-notch fa-spin text-primary-500"></i>
                        </div>

                        <div x-show="side === 'front'" x-html="sampleFront"></div>
                        <div x-show="side === 'back'" style="display: none;" x-html="sampleBack"></div>
                    </div>

                    <p class="text-center text-[11px] leading-snug text-gray-400 dark:text-gray-500">
                        Sample details. Real cards use each pupil&rsquo;s or staff member&rsquo;s own record.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
