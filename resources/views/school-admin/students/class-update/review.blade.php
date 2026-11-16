@php
    use App\Services\ClassRoster\RosterFields;

    $cardClass = 'rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]';
    $updates = $diff['updates'];
    $additions = $diff['additions'];
    $fieldCount = collect($updates)->sum(fn ($u) => count($u['changes']));
    $affected = count($updates);
    $size = (int) $diff['class_size'];
    $problemCount = count(collect($diff['errors'])->unique(fn ($e) => $e['key']));
    $hasAnything = $updates !== [] || $additions !== [] || $diff['new_columns'] !== [];
    $kinds = [
        'changed' => ['Changed', 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
        'added' => ['Added', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
        'cleared' => ['Cleared', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
    ];
    // Changes per field, to show which details changed across the class.
    $byField = collect($updates)->flatMap(fn ($u) => array_keys($u['changes']))->countBy()->sortDesc();
@endphp

<x-dashboard-layout page-title="Review Class Update" page-subtitle="Check every detected change before it is applied.">
    <div class="space-y-6" x-data="rosterReview()" x-init="recount()">
        <a href="{{ route('classes.update.show', $class) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 transition-colors duration-150 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to Update {{ $class->name }}
        </a>

        @include('school-admin.students.class-update._messages')

        {{-- Summary --}}
        <div class="{{ $cardClass }} p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Detected changes to {{ $class->name }}</h3>
                    <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        From <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $diff['file_name'] }}</span>. Nothing has been saved yet.
                    </small>
                    <small class="mt-1 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        Compared: {{ collect($diff['columns'])->map(fn ($c) => RosterFields::label($c))->implode(', ') }}.
                        Empty cells {{ $diff['blanks'] === 'clear' ? 'clear the detail on record' : 'leave the detail on record unchanged' }}.
                    </small>
                </div>
                <div class="flex flex-wrap gap-2 text-center">
                    @foreach ([
                        [$affected, Str::plural('student', $affected).' changed', 'blue'],
                        [$fieldCount, Str::plural('detail', $fieldCount).' changed', 'blue'],
                        [count($additions), 'new '.Str::plural('student', count($additions)), 'green'],
                        [$diff['unchanged'], 'unchanged', 'gray'],
                        [$problemCount, 'need attention', $problemCount ? 'red' : 'gray'],
                    ] as [$number, $label, $colour])
                        @php
                            $tile = [
                                'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                'green' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                'red' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                'gray' => 'bg-gray-50 text-gray-500 dark:bg-gray-700/50 dark:text-gray-300',
                            ][$colour];
                        @endphp
                        <div class="rounded-[8px] px-3 py-2 {{ $tile }}">
                            <p class="text-xl font-extrabold">{{ number_format($number) }}</p>
                            <small class="block text-[11px] font-medium">{{ $label }}</small>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- One student, several, or the whole class. --}}
            <div class="mt-4 flex gap-3 rounded-[8px] p-3 {{ $scope === 'all' ? 'bg-purple-50 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200' : ($scope === 'none' ? 'bg-gray-50 text-gray-700 dark:bg-gray-700/40 dark:text-gray-200' : 'bg-blue-50 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200') }}">
                <i class="fa-solid {{ $scope === 'all' ? 'fa-people-group' : ($scope === 'one' ? 'fa-user-pen' : ($scope === 'none' ? 'fa-circle-check' : 'fa-users')) }} mt-0.5 text-[14px]" aria-hidden="true"></i>
                <small class="block text-xs leading-relaxed">
                    @switch($scope)
                        @case('all')
                            <strong>The entire class has been updated:</strong> every one of the {{ number_format($size) }} students/pupils in {{ $class->name }} has at least one change. You can apply the changes to all of them at once, or review and untick any you do not want.
                            @break
                        @case('one')
                            <strong>One student/pupil is affected:</strong> {{ collect($updates)->first()['name'] ?? collect($diff['removals'])->first()['name'] ?? '' }}. Everyone else in {{ $class->name }} is left exactly as they are.
                            @break
                        @case('some')
                            @if ($affected > 0)
                                <strong>{{ number_format($affected) }} of {{ number_format($size) }} students/pupils are affected.</strong> Only they will be updated; the other {{ number_format(max(0, $size - $affected)) }} are left exactly as they are.
                            @else
                                <strong>No existing records change.</strong> The file only adds new students/pupils or columns.
                            @endif
                            @break
                        @default
                            <strong>No changes found.</strong> The file matches the records in {{ $class->name }}{{ $problemCount ? ', apart from the rows listed below that need attention' : '' }}.
                    @endswitch
                </small>
            </div>

            @if ($byField->isNotEmpty())
                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    <small class="text-xs text-gray-500 dark:text-gray-400">Details changing:</small>
                    @foreach ($byField as $field => $count)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ RosterFields::label($field) }} &middot; {{ $count }}</span>
                    @endforeach
                </div>
            @endif

            @if ($capacity && count($additions) > 0)
                <small class="mt-3 block text-xs text-gray-500 dark:text-gray-400">
                    Student allocation: {{ number_format($capacity['remaining']) }} of {{ number_format($capacity['allocated']) }} {{ Str::plural('space', $capacity['remaining']) }} left. If there is not room for every new student/pupil you select, nothing is applied.
                </small>
            @endif
        </div>

        <form id="discard-form" method="POST" action="{{ route('classes.update.cancel', ['class' => $class, 'token' => $token]) }}">
            @csrf @method('DELETE')
        </form>

        <form method="POST" action="{{ route('classes.update.apply', ['class' => $class, 'token' => $token]) }}" x-ref="form" @change="recount()" @submit="busy = true" class="space-y-6">
            @csrf

            {{-- New columns --}}
            @if ($diff['new_columns'] !== [])
                <div class="{{ $cardClass }} p-5">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">New columns</h3>
                    <small class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">These headings are not standard student details. Tick the ones to add to {{ $class->name }} as new columns; unticked ones are ignored.</small>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($diff['new_columns'] as $heading)
                            <label class="flex cursor-pointer items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 has-[:checked]:border-green-500 has-[:checked]:bg-green-50/60 dark:border-gray-600 dark:has-[:checked]:bg-green-900/20">
                                <input type="checkbox" name="new_columns[]" value="{{ $heading }}" checked class="h-3.5 w-3.5 rounded text-green-600" data-column="{{ $heading }}" @change="toggleColumn(@js($heading), $event.target.checked)">
                                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $heading }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Changed students --}}
            @if ($updates !== [])
                <div class="{{ $cardClass }}">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Students/pupils with changes ({{ number_format($affected) }})</h3>
                            <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Untick a student/pupil, or a single detail, to leave it as it is.</small>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="search" x-model="filter" placeholder="Find a student…" class="w-44 text-xs" aria-label="Find a student in the changes">
                            <button type="button" @click="setAll(true)" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Select all</button>
                            <button type="button" @click="setAll(false)" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Select none</button>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($updates as $uuid => $update)
                            <div class="px-5 py-4" x-data="{ on: true }" data-student x-show="matches(@js(mb_strtolower($update['name'].' '.$update['admission_number'])))">
                                <label class="flex cursor-pointer flex-wrap items-center gap-2">
                                    <input type="checkbox" x-model="on" data-student-toggle class="h-4 w-4 rounded text-blue-600" @change="$nextTick(() => recount())">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $update['name'] }}</span>
                                    @if ($update['admission_number'])
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $update['admission_number'] }}</span>
                                    @endif
                                    @if ($update['line'])
                                        <small class="text-xs text-gray-400">row {{ $update['line'] }}</small>
                                    @endif
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ count($update['changes']) }} {{ Str::plural('change', count($update['changes'])) }}</span>
                                </label>

                                <div class="mt-2 overflow-x-auto" :class="on ? '' : 'opacity-50'">
                                    <table class="w-full min-w-[520px] table-fixed text-left text-xs">
                                        <thead class="text-[11px] uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="w-8 py-1.5"></th>
                                                <th class="w-40 py-1.5 pr-3 font-semibold">Detail</th>
                                                <th class="py-1.5 pr-3 font-semibold">On record now</th>
                                                <th class="w-6 py-1.5"></th>
                                                <th class="py-1.5 pr-3 font-semibold">From the file</th>
                                                <th class="w-28 py-1.5 font-semibold"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($update['changes'] as $field => $change)
                                                <tr class="align-top" @if (RosterFields::isExtra($field)) data-column-field="{{ RosterFields::extraHeading($field) }}" @endif>
                                                    <td class="py-1.5">
                                                        <input type="checkbox" name="updates[{{ $uuid }}][]" value="{{ $field }}" checked data-field :disabled="! on" class="h-3.5 w-3.5 rounded text-blue-600" aria-label="Apply {{ RosterFields::label($field) }} for {{ $update['name'] }}">
                                                    </td>
                                                    <td class="py-1.5 pr-3 font-semibold text-gray-700 dark:text-gray-200">{{ RosterFields::label($field) }}</td>
                                                    <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">
                                                        @if ($change['old'] === null)
                                                            <span class="italic text-gray-400">empty</span>
                                                        @else
                                                            <span class="{{ $change['kind'] === 'cleared' ? 'line-through' : '' }}">{{ RosterFields::display($field, $change['old']) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 text-gray-400"><i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></td>
                                                    <td class="py-1.5 pr-3 font-semibold text-gray-900 dark:text-white">
                                                        @if ($change['new'] === null)
                                                            <span class="italic font-normal text-red-600 dark:text-red-400">will be cleared</span>
                                                        @else
                                                            {{ RosterFields::display($field, $change['new']) }}
                                                        @endif
                                                    </td>
                                                    <td class="py-1.5 text-right">
                                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $kinds[$change['kind']][1] }}">{{ $kinds[$change['kind']][0] }}</span>
                                                        @if ($field === 'class_name')
                                                            <small class="block text-[11px] text-amber-700 dark:text-amber-400">moves to {{ $change['new'] }}</small>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- New students --}}
            @if ($additions !== [])
                <div class="{{ $cardClass }}">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Not in {{ $class->name }} yet ({{ count($additions) }})</h3>
                        <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                            These rows do not match anyone in the class, so they will be added as new students/pupils. Untick any you do not want to add{{ $school->auto_generate_admission_numbers ? '; admission numbers are generated automatically' : '' }}.
                        </small>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-[11px] uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                <tr>
                                    <th class="w-10 px-4 py-2"></th>
                                    <th class="px-3 py-2 font-semibold">Row</th>
                                    @foreach ($diff['columns'] as $field)
                                        <th class="whitespace-nowrap px-3 py-2 font-semibold" @if (RosterFields::isExtra($field)) data-column-field="{{ RosterFields::extraHeading($field) }}" @endif>{{ RosterFields::label($field) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($additions as $key => $addition)
                                    <tr>
                                        <td class="px-4 py-2"><input type="checkbox" name="additions[]" value="{{ $key }}" checked data-addition class="h-3.5 w-3.5 rounded text-green-600" aria-label="Add {{ $addition['name'] }}"></td>
                                        <td class="px-3 py-2 font-mono text-gray-500">{{ $addition['line'] }}</td>
                                        @foreach ($diff['columns'] as $field)
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200" @if (RosterFields::isExtra($field)) data-column-field="{{ RosterFields::extraHeading($field) }}" @endif>{{ RosterFields::display($field, $addition['values'][$field] ?? null) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Rows that cannot be applied --}}
            @if ($diff['errors'] !== [])
                <div class="{{ $cardClass }}">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-red-700 dark:text-red-400">Need attention ({{ $problemCount }})</h3>
                        <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">These rows will not be applied. The records they refer to stay as they are. Correct the file and upload it again to include them.</small>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach (collect($diff['errors'])->groupBy('key') as $rowErrors)
                            <li class="px-5 py-3">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $rowErrors->first()['name'] }}
                                    @if ($rowErrors->first()['line'])<small class="font-normal text-gray-400">row {{ $rowErrors->first()['line'] }}</small>@endif
                                </p>
                                @foreach ($rowErrors as $error)
                                    <small class="block text-xs leading-relaxed text-red-700 dark:text-red-400">{{ $error['message'] }}</small>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($diff['warnings'] !== [])
                <div class="rounded-[5px] bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 lg:rounded-[10px]">
                    <p class="text-xs font-semibold">Notes</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        @foreach ($diff['warnings'] as $warning)
                            <li><small class="text-xs leading-relaxed">{{ $warning['name'] }}{{ $warning['line'] ? ' (row '.$warning['line'].')' : '' }}: {{ $warning['message'] }}</small></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($diff['not_in_file'] !== [])
                <details class="{{ $cardClass }} px-5 py-4">
                    <summary class="cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-200">
                        {{ count($diff['not_in_file']) }} {{ count($diff['not_in_file']) === 1 ? 'student/pupil' : 'students/pupils' }} in {{ $class->name }} {{ count($diff['not_in_file']) === 1 ? 'is' : 'are' }} not in the file, and will be left unchanged
                    </summary>
                    <ul class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($diff['not_in_file'] as $student)
                            <li><small class="text-xs text-gray-600 dark:text-gray-300">{{ $student['name'] }} @if ($student['admission_number'])<span class="font-mono text-gray-400">{{ $student['admission_number'] }}</span>@endif</small></li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Confirm --}}
            <div class="{{ $cardClass }} sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 p-4 shadow-lg">
                <small class="text-xs text-gray-600 dark:text-gray-300">
                    <span x-text="selectedFields"></span> <span x-text="selectedFields === 1 ? 'detail' : 'details'"></span> for <span x-text="selectedStudents"></span> <span x-text="selectedStudents === 1 ? 'student/pupil' : 'students/pupils'"></span>,
                    <span x-text="selectedAdditions"></span> new. A backup is taken first, so this can be rolled back.
                </small>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" form="discard-form" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                        <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Discard
                    </button>
                    @if ($hasAnything)
                        <button type="submit" :disabled="busy || (selectedFields + selectedAdditions + selectedColumns) === 0" class="btn inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            <i class="fa-solid fa-check text-[11px]" aria-hidden="true"></i>
                            Apply Selected Changes
                        </button>
                        @if ($scope === 'all')
                            <button type="submit" name="apply_all" value="1" :disabled="busy"
                                    onclick="return confirm(@js('Apply every detected change to all '.$size.' students/pupils in '.$class->name.'?'))"
                                    class="btn inline-flex items-center gap-2 rounded-[8px] bg-purple-600 px-4 py-2 text-xs font-semibold text-white hover:bg-purple-700 disabled:opacity-60">
                                <i class="fa-solid fa-people-group text-[11px]" aria-hidden="true"></i>
                                Apply to Entire Class
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </form>
    </div>

    <script>
        function rosterReview() {
            return {
                busy: false,
                filter: '',
                selectedFields: 0,
                selectedStudents: 0,
                selectedAdditions: 0,
                selectedColumns: 0,
                matches(text) {
                    return this.filter.trim() === '' || text.includes(this.filter.trim().toLowerCase());
                },
                recount() {
                    const form = this.$refs.form;
                    if (! form) return;
                    this.selectedFields = form.querySelectorAll('input[data-field]:checked:not(:disabled)').length;
                    this.selectedStudents = [...form.querySelectorAll('[data-student]')]
                        .filter(el => el.querySelector('input[data-field]:checked:not(:disabled)')).length;
                    this.selectedAdditions = form.querySelectorAll('input[data-addition]:checked').length;
                    this.selectedColumns = form.querySelectorAll('input[data-column]:checked').length;
                },
                setAll(on) {
                    this.$refs.form.querySelectorAll('input[data-student-toggle]').forEach(box => {
                        if (box.checked !== on) box.click();
                    });
                    this.$refs.form.querySelectorAll('input[data-field]').forEach(box => box.checked = on);
                    this.$nextTick(() => this.recount());
                },
                // An unticked new column takes its values out of the preview.
                toggleColumn(heading, on) {
                    this.$refs.form.querySelectorAll('[data-column-field]').forEach(el => {
                        if (el.dataset.columnField !== heading) return;
                        el.style.opacity = on ? '' : '0.35';
                        el.querySelectorAll('input[data-field]').forEach(box => box.checked = on);
                    });
                    this.$nextTick(() => this.recount());
                },
            };
        }
    </script>
</x-dashboard-layout>
