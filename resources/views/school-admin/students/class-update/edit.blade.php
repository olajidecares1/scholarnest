@php
    use App\Enums\Gender;

    $auto = (bool) $school->auto_generate_admission_numbers;

    // The grid's columns: what each cell is and how wide it starts.
    $widths = [
        'admission_number' => 150, 'first_name' => 150, 'last_name' => 150, 'gender' => 110,
        'date_of_birth' => 150, 'class_name' => 140, 'house' => 130, 'blood_group' => 110,
        'guardian_name' => 170, 'guardian_phone' => 140, 'guardian_email' => 200, 'phone' => 140,
        'email' => 200, 'address' => 240, 'admission_date' => 150, 'notes' => 220, 'is_active' => 120,
    ];
    $types = ['gender' => 'select', 'class_name' => 'select', 'is_active' => 'select', 'date_of_birth' => 'date', 'admission_date' => 'date', 'guardian_email' => 'email', 'email' => 'email', 'guardian_phone' => 'tel', 'phone' => 'tel'];
    $options = [
        'gender' => collect(Gender::cases())->map(fn ($g) => ['value' => $g->value, 'label' => $g->label()])->prepend(['value' => '', 'label' => '—'])->values()->all(),
        'class_name' => collect($classes)->map(fn ($c) => ['value' => $c, 'label' => $c])->values()->all(),
        'is_active' => [['value' => '1', 'label' => 'Active'], ['value' => '0', 'label' => 'Inactive']],
    ];

    $columns = [];
    foreach (\App\Services\ClassRoster\RosterFields::FIELDS as $key => $label) {
        $columns[] = [
            'key' => $key,
            'label' => $label,
            'type' => $types[$key] ?? 'text',
            'width' => $widths[$key] ?? 140,
            'readonly' => $key === 'admission_number' && $auto,
            'required' => in_array($key, ['first_name', 'last_name', 'gender'], true) || ($key === 'admission_number' && ! $auto),
            'options' => $options[$key] ?? null,
        ];
    }

    $oldRoster = old('roster') ? json_decode(old('roster'), true) : null;
    $oldColumns = old('columns') ? json_decode(old('columns'), true) : null;

    $editorConfig = [
        'rows' => $rows,
        'columns' => $columns,
        'extraColumns' => $extraColumns,
        'className' => $class->name,
        'auto' => $auto,
        'oldRoster' => is_array($oldRoster) ? $oldRoster : null,
        'oldColumns' => is_array($oldColumns) ? $oldColumns : null,
        'errors' => session('roster_errors', []),
        'remaining' => $capacity['remaining'] ?? null,
    ];
@endphp

<x-dashboard-layout page-title="Edit Class" page-subtitle="Edit the whole class at once, then save your changes.">
    <div class="space-y-4" x-data="classEditor(@js($editorConfig))" @keydown.window.ctrl.s.prevent="openReview()" @keydown.window.meta.s.prevent="openReview()" @beforeunload.window="warnUnsaved($event)">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('classes.update.show', $class) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to Update {{ $class->name }}
            </a>
            <small class="text-xs text-gray-500 dark:text-gray-400">Tip: paste cells copied from Excel straight into the grid. Enter moves down; Ctrl+S saves.</small>
        </div>

        @include('school-admin.students.class-update._messages')

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="mr-2 text-sm font-bold text-gray-900 dark:text-white">{{ $class->name }}</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300" x-text="activeCount() + ' ' + (activeCount() === 1 ? 'student/pupil' : 'students/pupils')"></span>
                    <input type="search" x-model="filter" placeholder="Find a student…" class="w-48 text-xs" aria-label="Find a student in the class">
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="addRow()" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-green-600 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20">
                        <i class="fa-solid fa-user-plus text-[11px]" aria-hidden="true"></i> Add Student
                    </button>
                    <div class="relative" x-data="{ open: false, heading: '' }" @click.outside="open = false">
                        <button type="button" @click="open = ! open; $nextTick(() => $refs.heading?.focus())" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-table-columns text-[11px]" aria-hidden="true"></i> Add Column
                        </button>
                        <div x-cloak x-show="open" x-transition class="absolute right-0 z-40 mt-2 w-72 rounded-[10px] border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <label class="field-label" for="new-column">Column heading</label>
                            <input id="new-column" x-ref="heading" type="text" x-model="heading" maxlength="50" placeholder="e.g. Bus Stop" class="mt-1 w-full text-xs"
                                   @keydown.enter.prevent="if (addColumn(heading)) { heading = ''; open = false }">
                            <small class="mt-1 block text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">For information beyond the standard details. Kept for {{ $class->name }} only.</small>
                            <div class="mt-2 flex justify-end">
                                <button type="button" @click="if (addColumn(heading)) { heading = ''; open = false }" class="btn rounded-[8px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Add</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" @click="discard()" x-show="pendingCount() > 0" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <i class="fa-solid fa-rotate-left text-[11px]" aria-hidden="true"></i> Undo All
                    </button>
                    <button type="button" @click="openReview()" :disabled="pendingCount() === 0" class="btn inline-flex items-center gap-1.5 rounded-[8px] bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        <i class="fa-solid fa-floppy-disk text-[11px]" aria-hidden="true"></i>
                        Save Changes
                        <span x-show="pendingCount() > 0" class="rounded-full bg-white/25 px-1.5 text-[11px]" x-text="pendingCount()"></span>
                    </button>
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-4 py-2 text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-amber-100 ring-1 ring-amber-300 dark:bg-amber-900/40"></span> changed</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-green-100 ring-1 ring-green-300 dark:bg-green-900/40"></span> new student/pupil</span>
                <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm bg-red-100 ring-1 ring-red-300 dark:bg-red-900/40"></span> to be removed from class / needs correcting</span>
                <span x-show="changedStudents() > 0" class="ml-auto font-medium text-gray-700 dark:text-gray-200"
                      x-text="changedStudents() + ' changed · ' + newRows().length + ' new · ' + removedRows().length + ' removed'"></span>
            </div>

            {{-- The grid --}}
            <div class="max-h-[70vh] overflow-auto" x-ref="grid">
                <table class="roster-grid border-separate border-spacing-0 text-xs">
                    <thead class="sticky top-0 z-20">
                        <tr>
                            <th class="sticky left-0 z-30 w-12 border-b border-r border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">#</th>
                            <template x-for="column in allColumns()" :key="column.key">
                                <th class="whitespace-nowrap border-b border-r border-gray-200 bg-gray-100 px-2 py-2 text-left font-semibold text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    :class="column.key === 'first_name' ? 'sticky left-12 z-30' : ''"
                                    :style="`min-width: ${column.width}px; max-width: ${column.width}px`">
                                    <span class="flex items-center justify-between gap-1">
                                        <span>
                                            <span x-text="column.label"></span><span x-show="column.required" class="text-red-500"> *</span>
                                            <i x-show="column.readonly" class="fa-solid fa-lock ml-1 text-[9px] text-gray-400" title="Generated automatically"></i>
                                            <span x-show="column.extra && column.isNew" class="ml-1 rounded bg-green-100 px-1 text-[10px] text-green-700 dark:bg-green-900/40 dark:text-green-300">new</span>
                                        </span>
                                        <button x-show="column.extra" type="button" @click="removeColumn(column.heading)" class="text-gray-400 hover:text-red-600" :aria-label="'Remove the column ' + column.heading" title="Remove this column">
                                            <i class="fa-solid fa-xmark text-[11px]" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                </th>
                            </template>
                            <th class="w-24 border-b border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">Row</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, r) in rows" :key="row.key">
                            <tr x-show="visible(row)" :class="row.removed ? 'opacity-60' : ''">
                                <td class="sticky left-0 z-10 border-b border-r border-gray-200 px-2 py-1 text-center font-mono text-[11px] text-gray-500 dark:border-gray-700"
                                    :class="row.removed ? 'bg-red-50 dark:bg-red-900/30' : (row.isNew ? 'bg-green-50 dark:bg-green-900/30' : 'bg-gray-50 dark:bg-gray-900')"
                                    :title="rowError(row) || ''">
                                    <span x-text="r + 1"></span>
                                    <i x-show="rowError(row)" class="fa-solid fa-circle-exclamation ml-0.5 text-red-600"></i>
                                </td>
                                <template x-for="column in allColumns()" :key="column.key">
                                    <td class="border-b border-r border-gray-200 p-0 dark:border-gray-700"
                                        :class="[cellClass(row, column), column.key === 'first_name' ? 'sticky left-12 z-10' : '']"
                                        :title="cellError(row, column) || (changed(row, column) ? 'Was: ' + (displayOriginal(row, column) || 'empty') : '')">
                                        <template x-if="column.type === 'select'">
                                            <select class="roster-cell" x-model="row.values[column.key]" :disabled="row.removed"
                                                    :data-row="r" :data-col="column.key" @keydown="navigate($event, r, column.key)" :aria-label="column.label + ', row ' + (r + 1)">
                                                <template x-if="column.key === 'class_name' && ! column.options.some(o => o.value === row.values[column.key])">
                                                    <option :value="row.values[column.key]" x-text="row.values[column.key] || '—'"></option>
                                                </template>
                                                <template x-for="option in column.options" :key="option.value">
                                                    <option :value="option.value" x-text="option.label" :selected="option.value === row.values[column.key]"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="column.type !== 'select'">
                                            <input class="roster-cell" :type="column.type" x-model="row.values[column.key]"
                                                   :readonly="column.readonly" :disabled="row.removed"
                                                   :placeholder="column.readonly && row.isNew ? 'automatic' : ''"
                                                   :data-row="r" :data-col="column.key"
                                                   @keydown="navigate($event, r, column.key)"
                                                   @paste="paste($event, r, column.key)"
                                                   :aria-label="column.label + ', row ' + (r + 1)">
                                        </template>
                                    </td>
                                </template>
                                <td class="border-b border-gray-200 px-2 py-1 text-center dark:border-gray-700">
                                    <template x-if="! row.removed">
                                        <span class="inline-flex items-center gap-1">
                                            <button type="button" x-show="rowChanged(row) && ! row.isNew" @click="resetRow(row)" class="icon-btn icon-btn--neutral" title="Undo changes to this row" aria-label="Undo changes to this row"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
                                            <button type="button" @click="removeRow(row)" class="icon-btn icon-btn--delete" :title="row.isNew ? 'Delete this new row' : 'Remove from ' + className" :aria-label="row.isNew ? 'Delete this new row' : 'Remove from ' + className"><i class="fa-solid fa-user-minus" aria-hidden="true"></i></button>
                                        </span>
                                    </template>
                                    <template x-if="row.removed">
                                        <button type="button" @click="row.removed = false" class="text-[11px] font-semibold text-blue-600 hover:underline">Keep</button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0">
                            <td :colspan="allColumns().length + 2" class="px-4 py-8 text-center text-gray-500">No students/pupils in {{ $class->name }} yet. Use Add Student to add some.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                <button type="button" @click="addRow()" class="text-xs font-semibold text-green-700 hover:underline dark:text-green-400">
                    <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i> Add another student/pupil
                </button>
                <small class="text-xs text-gray-500 dark:text-gray-400">
                    Removing a student/pupil takes them out of {{ $class->name }} only; their record is kept. Every save is backed up and can be rolled back.
                </small>
            </div>
        </div>

        {{-- Confirm before saving --}}
        <div x-cloak x-show="reviewing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="reviewing = false">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-[10px] bg-white p-5 shadow-xl dark:bg-gray-800" @click.outside="reviewing = false" role="dialog" aria-modal="true" aria-labelledby="save-title">
                <h3 id="save-title" class="text-sm font-bold text-gray-900 dark:text-white">Save changes to {{ $class->name }}?</h3>
                <ul class="mt-3 space-y-1.5 text-xs text-gray-700 dark:text-gray-200">
                    <li x-show="changedStudents() > 0"><i class="fa-solid fa-user-pen mr-1.5 w-4 text-blue-600"></i><span x-text="changedStudents() + ' ' + (changedStudents() === 1 ? 'student/pupil' : 'students/pupils') + ' updated (' + changedCells() + ' ' + (changedCells() === 1 ? 'detail' : 'details') + ')'"></span></li>
                    <li x-show="newRows().length > 0"><i class="fa-solid fa-user-plus mr-1.5 w-4 text-green-600"></i><span x-text="newRows().length + ' new ' + (newRows().length === 1 ? 'student/pupil' : 'students/pupils') + ' added'"></span></li>
                    <li x-show="removedRows().length > 0"><i class="fa-solid fa-user-minus mr-1.5 w-4 text-red-600"></i><span x-text="removedRows().length + ' taken out of ' + className + ' (records kept)'"></span></li>
                    <li x-show="addedColumns().length > 0"><i class="fa-solid fa-table-columns mr-1.5 w-4 text-green-600"></i><span x-text="'New ' + (addedColumns().length === 1 ? 'column' : 'columns') + ': ' + addedColumns().join(', ')"></span></li>
                    <li x-show="droppedColumns().length > 0"><i class="fa-solid fa-table-columns mr-1.5 w-4 text-red-600"></i><span x-text="'Removed ' + (droppedColumns().length === 1 ? 'column' : 'columns') + ' (values cleared): ' + droppedColumns().join(', ')"></span></li>
                </ul>
                <template x-if="changedStudents() > 0 && changedStudents() >= rows.filter(r => ! r.isNew).length">
                    <p class="mt-3 rounded-[8px] bg-purple-50 p-2 text-xs text-purple-800 dark:bg-purple-900/30 dark:text-purple-200">This updates every student/pupil in the class.</p>
                </template>
                <small class="mt-3 block text-xs text-gray-500 dark:text-gray-400">A backup is taken first, so this can be rolled back from the Update Class page.</small>

                <form method="POST" action="{{ route('classes.update.save', $class) }}" class="mt-4 flex justify-end gap-2" @submit="saving = true; dirtyGuard = false">
                    @csrf
                    <input type="hidden" name="roster" :value="payload()">
                    <input type="hidden" name="columns" :value="JSON.stringify(extraColumns.map(c => c.heading))">
                    <button type="button" @click="reviewing = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Keep Editing</button>
                    <button type="submit" :disabled="saving" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                        <i x-show="! saving" class="fa-solid fa-check text-[11px]" aria-hidden="true"></i>
                        <i x-show="saving" style="display: none;" class="fa-solid fa-spinner fa-spin text-[11px]" aria-hidden="true"></i>
                        Update Class
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .roster-grid .roster-cell {
            display: block;
            width: 100%;
            min-height: 32px;
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 6px 8px;
            font-size: 12px;
            box-shadow: none;
        }
        .roster-grid .roster-cell:focus {
            outline: 2px solid #2563eb;
            outline-offset: -2px;
            background: rgba(37, 99, 235, 0.06);
        }
        .roster-grid .roster-cell[readonly] { color: #6b7280; cursor: default; }
        .roster-grid .roster-cell:disabled { text-decoration: line-through; cursor: not-allowed; }
        .roster-grid td { background-clip: padding-box; }
    </style>

    <script>
        function classEditor(config) {
            const blank = () => Object.fromEntries(config.columns.map(c => [c.key, c.key === 'is_active' ? '1' : (c.key === 'class_name' ? config.className : '')]));
            let counter = 0;

            const originals = {};
            config.rows.forEach(row => originals[row.key] = row.values);

            return {
                className: config.className,
                columns: config.columns,
                extraColumns: config.extraColumns.map(heading => ({ heading, isNew: false })),
                originalExtra: [...config.extraColumns],
                rows: [],
                filter: '',
                reviewing: false,
                saving: false,
                dirtyGuard: true,
                errors: {},

                init() {
                    // Back from a save that needed correcting: the school's
                    // edits come back exactly as they were.
                    if (config.oldColumns) {
                        this.extraColumns = config.oldColumns.map(heading => ({ heading, isNew: ! this.originalExtra.includes(heading) }));
                    }

                    if (config.oldRoster) {
                        this.rows = config.oldRoster.map(row => this.makeRow(row.key, row.uuid || null, row.values || {}, !! row.removed));
                        config.rows.forEach(row => {
                            if (! this.rows.some(r => r.key === row.key)) this.rows.push(this.makeRow(row.key, row.uuid, row.values, false));
                        });
                    } else {
                        this.rows = config.rows.map(row => this.makeRow(row.key, row.uuid, row.values, false));
                    }

                    (config.errors || []).forEach(error => {
                        this.errors[error.key] ??= {};
                        this.errors[error.key][error.field || '_row'] = error.message;
                    });
                },

                makeRow(key, uuid, values, removed) {
                    const original = uuid ? (originals[key] || {}) : null;
                    const merged = { ...blank(), ...(original || {}) };
                    Object.entries(values || {}).forEach(([k, v]) => merged[k] = v == null ? '' : String(v));

                    return { key, uuid, isNew: ! uuid, removed, values: merged, original };
                },

                allColumns() {
                    return [
                        ...this.columns,
                        ...this.extraColumns.map(c => ({ key: 'x:' + c.heading, heading: c.heading, label: c.heading, type: 'text', width: 160, extra: true, isNew: c.isNew, readonly: false })),
                    ];
                },

                visible(row) {
                    const q = this.filter.trim().toLowerCase();
                    if (q === '') return true;
                    return [row.values.first_name, row.values.last_name, row.values.admission_number].join(' ').toLowerCase().includes(q);
                },

                originalValue(row, column) {
                    return row.original ? (row.original[column.key] ?? '') : '';
                },

                changed(row, column) {
                    if (row.isNew || column.readonly) return false;
                    return String(row.values[column.key] ?? '').trim() !== String(this.originalValue(row, column)).trim();
                },

                displayOriginal(row, column) {
                    const value = this.originalValue(row, column);
                    const option = (column.options || []).find(o => o.value === value);
                    return option ? option.label : value;
                },

                rowChanged(row) {
                    return ! row.isNew && this.allColumns().some(c => this.changed(row, c));
                },

                rowFilled(row) {
                    return this.columns.some(c => ! ['is_active', 'class_name'].includes(c.key) && String(row.values[c.key] ?? '').trim() !== '')
                        || this.extraColumns.some(c => String(row.values['x:' + c.heading] ?? '').trim() !== '');
                },

                cellError(row, column) {
                    return this.errors[row.key]?.[column.key] || null;
                },

                rowError(row) {
                    const errors = this.errors[row.key];
                    return errors ? Object.values(errors).join(' ') : null;
                },

                cellClass(row, column) {
                    if (this.cellError(row, column)) return 'bg-red-50 ring-1 ring-inset ring-red-400 dark:bg-red-900/30';
                    if (row.removed) return 'bg-red-50/70 dark:bg-red-900/20';
                    if (row.isNew) return 'bg-green-50/70 dark:bg-green-900/20';
                    if (this.changed(row, column)) return 'bg-amber-50 dark:bg-amber-900/30';
                    return 'bg-white dark:bg-gray-800';
                },

                activeCount() {
                    return this.rows.filter(r => ! r.removed && (! r.isNew || this.rowFilled(r))).length;
                },
                changedStudents() {
                    return this.rows.filter(r => ! r.removed && this.rowChanged(r)).length;
                },
                changedCells() {
                    return this.rows.filter(r => ! r.removed && ! r.isNew)
                        .reduce((sum, r) => sum + this.allColumns().filter(c => this.changed(r, c)).length, 0);
                },
                newRows() {
                    return this.rows.filter(r => r.isNew && ! r.removed && this.rowFilled(r));
                },
                removedRows() {
                    return this.rows.filter(r => r.removed && ! r.isNew);
                },
                addedColumns() {
                    return this.extraColumns.filter(c => ! this.originalExtra.includes(c.heading)).map(c => c.heading);
                },
                droppedColumns() {
                    return this.originalExtra.filter(h => ! this.extraColumns.some(c => c.heading === h));
                },
                pendingCount() {
                    return this.changedCells() + this.newRows().length + this.removedRows().length + this.addedColumns().length + this.droppedColumns().length;
                },

                addRow() {
                    const key = 'new-' + Date.now() + '-' + (counter++);
                    this.rows.push(this.makeRow(key, null, {}, false));
                    this.filter = '';
                    this.$nextTick(() => this.focus(this.rows.length - 1, this.auto() ? 'first_name' : 'admission_number'));
                },

                auto() {
                    return config.auto;
                },

                removeRow(row) {
                    if (row.isNew) {
                        this.rows = this.rows.filter(r => r !== row);
                        return;
                    }
                    row.removed = true;
                },

                resetRow(row) {
                    row.values = { ...blank(), ...(row.original || {}) };
                    delete this.errors[row.key];
                },

                discard() {
                    if (! confirm('Undo every change you have made in the editor?')) return;
                    this.rows = config.rows.map(row => this.makeRow(row.key, row.uuid, row.values, false));
                    this.extraColumns = this.originalExtra.map(heading => ({ heading, isNew: false }));
                    this.errors = {};
                },

                addColumn(heading) {
                    heading = String(heading || '').replace(/\s+/g, ' ').trim().slice(0, 50);
                    if (heading === '') return false;
                    const taken = [...this.columns.map(c => c.label), ...this.extraColumns.map(c => c.heading)].map(h => h.toLowerCase());
                    if (taken.includes(heading.toLowerCase())) {
                        alert('There is already a column called "' + heading + '".');
                        return false;
                    }
                    if (this.extraColumns.length >= 20) {
                        alert('A class can have up to 20 added columns.');
                        return false;
                    }
                    this.extraColumns.push({ heading, isNew: ! this.originalExtra.includes(heading) });
                    this.rows.forEach(r => r.values['x:' + heading] ??= '');
                    this.$nextTick(() => this.$refs.grid.scrollLeft = this.$refs.grid.scrollWidth);
                    return true;
                },

                removeColumn(heading) {
                    const used = this.rows.some(r => String(r.values['x:' + heading] ?? '').trim() !== '');
                    if (used && ! confirm('Remove the column "' + heading + '"? Its values are cleared for everyone in ' + this.className + ' when you save (an update you can roll back).')) return;
                    this.extraColumns = this.extraColumns.filter(c => c.heading !== heading);
                },

                // Moving around like a spreadsheet.
                focus(r, key) {
                    const el = this.$refs.grid.querySelector(`[data-row="${r}"][data-col="${CSS.escape(key)}"]`);
                    if (el) { el.focus(); if (el.select && el.type !== 'date') el.select(); }
                },
                navigate(event, r, key) {
                    const isSelect = event.target.tagName === 'SELECT';
                    let next = null;
                    if (event.key === 'Enter') { event.preventDefault(); next = event.shiftKey ? r - 1 : r + 1; }
                    else if (event.key === 'ArrowDown' && (! isSelect || event.ctrlKey)) { event.preventDefault(); next = r + 1; }
                    else if (event.key === 'ArrowUp' && (! isSelect || event.ctrlKey)) { event.preventDefault(); next = r - 1; }
                    if (next === null) return;
                    while (next >= 0 && next < this.rows.length && ! this.visible(this.rows[next])) next += next > r ? 1 : -1;
                    if (next >= this.rows.length && event.key === 'Enter') { this.addRow(); return; }
                    if (next >= 0 && next < this.rows.length) this.focus(next, key);
                },

                // A block of cells copied from Excel or Word fills the grid
                // from the cell pasted into, adding rows as needed.
                paste(event, r, key) {
                    const text = (event.clipboardData || window.clipboardData).getData('text');
                    if (! text || (! text.includes('\t') && ! text.includes('\n'))) return;
                    event.preventDefault();

                    const lines = text.replace(/\r/g, '').replace(/\n$/, '').split('\n').map(line => line.split('\t'));
                    const columns = this.allColumns();
                    const start = columns.findIndex(c => c.key === key);

                    lines.forEach((cells, i) => {
                        let index = r + i;
                        while (index >= this.rows.length) this.rows.push(this.makeRow('new-' + Date.now() + '-' + (counter++), null, {}, false));
                        const row = this.rows[index];
                        if (row.removed) return;
                        cells.forEach((cell, j) => {
                            const column = columns[start + j];
                            if (column && ! column.readonly) row.values[column.key] = this.normalise(column, cell.trim());
                        });
                    });
                },

                normalise(column, value) {
                    if (value === '') return '';
                    if (column.type === 'date') {
                        const m = value.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
                        if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
                        return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '';
                    }
                    if (column.key === 'gender') {
                        const g = value.toLowerCase();
                        return ['m', 'male', 'boy'].includes(g) ? 'male' : (['f', 'female', 'girl'].includes(g) ? 'female' : '');
                    }
                    if (column.key === 'is_active') {
                        return /^(inactive|no|0|false)$/i.test(value) ? '0' : '1';
                    }
                    if (column.key === 'class_name') {
                        const match = column.options.find(o => o.value.toLowerCase().replace(/\s+/g, '') === value.toLowerCase().replace(/\s+/g, ''));
                        return match ? match.value : value;
                    }
                    return value;
                },

                openReview() {
                    if (this.pendingCount() === 0) return;
                    this.reviewing = true;
                },

                payload() {
                    return JSON.stringify(this.rows
                        .filter(r => ! (r.isNew && (r.removed || ! this.rowFilled(r))))
                        .map(r => ({ key: r.key, uuid: r.uuid, removed: r.removed, values: r.values })));
                },

                warnUnsaved(event) {
                    if (this.dirtyGuard && this.pendingCount() > 0) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                },
            };
        }
    </script>
</x-dashboard-layout>
