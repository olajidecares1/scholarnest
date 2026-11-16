@php
    $cardClass = 'rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]';
@endphp

<x-dashboard-layout page-title="Update Class" page-subtitle="Change a whole class at once, from a file or in the class editor.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('students.index', ['class' => $class->name]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Back to Students
            </a>

            {{-- Another class, without going back to the list. --}}
            <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-semibold">Class</span>
                <select class="w-48" onchange="if (this.value) window.location = this.value" aria-label="Choose a class to update">
                    @foreach ($classes as $option)
                        <option value="{{ route('classes.update.show', $option) }}" @selected($option->id === $class->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        @include('school-admin.students.class-update._messages')

        <div class="{{ $cardClass }} flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                    <i class="fa-solid fa-people-group text-[18px]" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $class->name }}</h2>
                    <small class="block text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($studentCount) }} {{ $studentCount === 1 ? 'student/pupil' : 'students/pupils' }}
                        @if (! empty($class->extra_columns))
                            &middot; added columns: {{ implode(', ', $class->extra_columns) }}
                        @endif
                    </small>
                </div>
            </div>
            <small class="max-w-md text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                Choose how to update the class. Either way, only the students/pupils and details that actually change are touched, and a backup is kept so any update can be rolled back.
            </small>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Option 1: upload a file --}}
            <div class="{{ $cardClass }} flex flex-col" id="upload">
                <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">1</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Upload File to Update Class</h3>
                        <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            Upload an Excel or Word file with the updated records. The system compares it with {{ $class->name }} and shows you every change, student by student, before anything is saved.
                        </small>
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[8px] bg-gray-50 px-3 py-3 dark:bg-gray-700/40">
                        <small class="block max-w-xs text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                            <span class="font-semibold text-gray-800 dark:text-gray-100">Easiest:</span> download {{ $class->name }} as it is now, edit it, and upload it back.
                        </small>
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                <i class="fa-solid fa-download text-[12px] leading-none" aria-hidden="true"></i>
                                Download Class List
                                <i class="fa-solid fa-chevron-down text-[10px] leading-none transition-transform duration-200" :class="open && 'rotate-180'" aria-hidden="true"></i>
                            </button>
                            <div x-cloak x-show="open" x-transition role="menu"
                                 class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-[10px] border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                @foreach ([
                                    'xlsx' => ['Excel spreadsheet', '.xlsx', 'fa-file-excel', 'text-green-600'],
                                    'csv' => ['CSV spreadsheet', '.csv', 'fa-file-csv', 'text-emerald-600'],
                                    'docx' => ['Word document', '.docx', 'fa-file-word', 'text-blue-600'],
                                ] as $format => [$label, $extension, $icon, $colour])
                                    <a href="{{ route('classes.update.export', ['class' => $class, 'format' => $format]) }}" role="menuitem" @click="open = false"
                                       class="flex items-center gap-3 px-3 py-2 text-xs text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                        <i class="fa-solid {{ $icon }} {{ $colour }} w-4 text-center text-[14px] leading-none" aria-hidden="true"></i>
                                        <span class="flex-1 font-semibold">{{ $label }}</span>
                                        <small class="text-[11px] text-gray-400">{{ $extension }}</small>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('classes.update.upload', $class) }}" enctype="multipart/form-data" class="space-y-5"
                          x-data="{ fileName: '', dragging: false, busy: false }" @submit="busy = true">
                        @csrf

                        <div>
                            <label for="roster_file" class="field-label">Updated class list<span class="text-red-500"> *</span></label>
                            <div class="relative mt-1.5 flex min-h-[120px] flex-col items-center justify-center rounded-[10px] border-2 border-dashed px-4 py-5 text-center transition-colors"
                                 :class="dragging ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-900/20' : (fileName ? 'border-green-400 bg-green-50/50 dark:border-green-700 dark:bg-green-900/10' : 'border-gray-300 hover:border-blue-400 dark:border-gray-600')"
                                 @dragover="dragging = true" @dragleave="dragging = false" @drop="dragging = false">
                                <input id="roster_file" type="file" name="file" required accept=".csv,.txt,.xlsx,.docx,.pdf"
                                       class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                       @change="fileName = $event.target.files[0]?.name || ''">
                                <i class="fa-solid text-[22px]" :class="fileName ? 'fa-file-circle-check text-green-600' : 'fa-file-arrow-up text-blue-500'" aria-hidden="true"></i>
                                <span class="mt-2 text-xs font-medium text-gray-800 dark:text-gray-100" x-show="! fileName">
                                    <span class="text-blue-600 dark:text-blue-400">Choose a file</span> or drag it here
                                </span>
                                <span class="mt-2 max-w-full truncate text-xs font-medium text-gray-800 dark:text-gray-100" x-show="fileName" x-text="fileName" style="display: none;"></span>
                                <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">Excel, Word, CSV or PDF &middot; up to 5MB</small>
                            </div>
                        </div>

                        <fieldset>
                            <legend class="field-label">Empty cells in the file</legend>
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ([
                                    'keep' => ['Keep what is on record', 'A blank cell changes nothing'],
                                    'clear' => ['Clear what is on record', 'A blank cell removes the detail'],
                                ] as $value => [$title, $hint])
                                    <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 transition-colors hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60 dark:border-gray-600 dark:has-[:checked]:bg-blue-900/20">
                                        <input type="radio" name="blanks" value="{{ $value }}" class="h-3.5 w-3.5 text-blue-600" @checked(old('blanks', 'keep') === $value)>
                                        <span class="leading-tight">
                                            <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ $title }}</span>
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="field-label">If full names are in one "Name" column</legend>
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach (['surname_first' => ['Surname first', 'Okafor Chinedu'], 'first_name_first' => ['First name first', 'Chinedu Okafor']] as $value => [$title, $example])
                                    <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 transition-colors hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/60 dark:border-gray-600 dark:has-[:checked]:bg-blue-900/20">
                                        <input type="radio" name="name_order" value="{{ $value }}" class="h-3.5 w-3.5 text-blue-600" @checked(old('name_order', 'surname_first') === $value)>
                                        <span class="leading-tight">
                                            <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ $title }}</span>
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">e.g. "{{ $example }}"</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <ul class="space-y-1 text-gray-500 dark:text-gray-400">
                            <li><small class="text-xs leading-relaxed"><i class="fa-solid fa-check mr-1 text-green-600" aria-hidden="true"></i>Rows are matched by Admission Number, or by name when there is none.</small></li>
                            <li><small class="text-xs leading-relaxed"><i class="fa-solid fa-check mr-1 text-green-600" aria-hidden="true"></i>Only the columns in your file are compared; everything else stays as it is.</small></li>
                            <li><small class="text-xs leading-relaxed"><i class="fa-solid fa-check mr-1 text-green-600" aria-hidden="true"></i>New rows can be added as new students/pupils, and new columns as new class columns.</small></li>
                        </ul>

                        <div class="flex justify-end">
                            <button type="submit" :disabled="busy" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                <i x-show="! busy" class="fa-solid fa-magnifying-glass-chart text-[12px] leading-none" aria-hidden="true"></i>
                                <i x-show="busy" style="display: none;" class="fa-solid fa-spinner fa-spin text-[12px] leading-none" aria-hidden="true"></i>
                                <span x-text="busy ? 'Analysing…' : 'Analyse File'">Analyse File</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Option 2: the class editor --}}
            <div class="{{ $cardClass }} flex flex-col">
                <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">2</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Edit Class Within the System</h3>
                        <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            Open the whole of {{ $class->name }} in a spreadsheet-style editor and change it directly.
                        </small>
                    </div>
                </div>

                <div class="flex flex-1 flex-col justify-between gap-5 px-5 py-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['fa-table-cells', 'Every student in one grid', 'Edit any cell, for as many students/pupils as you like, then save once.'],
                            ['fa-paste', 'Paste from Excel', 'Copy cells from a spreadsheet and paste them straight into the grid.'],
                            ['fa-user-plus', 'Add students/pupils', 'Add rows for new students/pupils, or take students out of the class.'],
                            ['fa-table-columns', 'Add columns', 'Add your own columns (e.g. "Bus Stop") to keep extra information.'],
                        ] as [$icon, $title, $text])
                            <div class="flex gap-3 rounded-[8px] border border-gray-100 p-3 dark:border-gray-700">
                                <i class="fa-solid {{ $icon }} mt-0.5 w-4 text-center text-[14px] text-blue-600" aria-hidden="true"></i>
                                <div>
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $title }}</p>
                                    <small class="block text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $text }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <a href="{{ route('classes.update.edit', $class) }}" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                            <i class="fa-solid fa-pen-to-square text-[12px] leading-none" aria-hidden="true"></i>
                            Open Class Editor
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- History and rollback --}}
        <div class="{{ $cardClass }}" id="history">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Update history and backups</h3>
                    <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        A backup is taken automatically before every update. Roll an update back to restore the records it changed; anything edited again since is kept as it is now.
                    </small>
                </div>
            </div>

            @if ($batches->isEmpty())
                <p class="px-5 py-6 text-center"><small class="text-xs text-gray-500 dark:text-gray-400">No bulk updates to {{ $class->name }} yet.</small></p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">When</th>
                                <th class="px-4 py-3 font-semibold">How</th>
                                <th class="px-4 py-3 font-semibold">What changed</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($batches as $batch)
                                <tr class="align-top {{ $batch->isRolledBack() ? 'bg-gray-50/70 dark:bg-gray-900/20' : '' }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                        {{ $batch->created_at?->format('d/m/Y H:i') }}
                                        @if ($batch->user_name)
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">by {{ $batch->user_name }}</small>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                        @if ($batch->source === \App\Models\ClassUpdateBatch::SOURCE_FILE)
                                            <i class="fa-solid fa-file-arrow-up mr-1 text-blue-500" aria-hidden="true"></i> File
                                            @if ($batch->file_name)
                                                <small class="block max-w-[14rem] truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $batch->file_name }}">{{ $batch->file_name }}</small>
                                            @endif
                                        @else
                                            <i class="fa-solid fa-table-cells mr-1 text-blue-500" aria-hidden="true"></i> Class editor
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                        {{ $batch->describe() }}
                                        @if (($batch->summary['scope'] ?? null) === 'all')
                                            <span class="ml-1 rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">Whole class</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        @if ($batch->isRolledBack())
                                            <span class="rounded-full bg-gray-200 px-2 py-0.5 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">Rolled back</span>
                                            <small class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $batch->rolled_back_at?->format('d/m/Y H:i') }}</small>
                                            @foreach ($batch->rollback_notes ?? [] as $note)
                                                <small class="mt-1 block max-w-xs text-xs leading-relaxed text-amber-700 dark:text-amber-400">{{ $note }}</small>
                                            @endforeach
                                        @else
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">Applied</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('classes.update.backup', ['class' => $class, 'batch' => $batch]) }}" class="icon-btn icon-btn--neutral" data-tooltip="Download backup" aria-label="Download the backup of this update">
                                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            </a>
                                            @unless ($batch->isRolledBack())
                                                <form method="POST" action="{{ route('classes.update.rollback', ['class' => $class, 'batch' => $batch]) }}"
                                                      onsubmit="return confirm(@js('Roll back this update to '.$class->name.'? The records it changed go back to how they were before it'.(($batch->summary['added'] ?? 0) > 0 ? ', and the '.$batch->summary['added'].' student(s)/pupil(s) it added will be deleted' : '').'. Details edited again since are kept.'))">
                                                    @csrf
                                                    <button type="submit" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-amber-500 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-900/20">
                                                        <i class="fa-solid fa-rotate-left text-[11px]" aria-hidden="true"></i> Roll Back
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
