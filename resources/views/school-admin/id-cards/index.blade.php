@php
    $tabs = [
        'student' => ['Students', 'fa-user-graduate'],
        'teaching' => ['Teaching Staff', 'fa-chalkboard-user'],
        'non_teaching' => ['Non-Teaching Staff', 'fa-user-tie'],
    ];
    $staffDepartments = fn ($staff) => $staff->pluck('department')->filter()->unique()->sort()->values();
@endphp

<x-dashboard-layout page-title="ID Card Management" page-subtitle="Generate, preview and print ID cards for students and staff, one at a time or in bulk.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="inline-flex flex-wrap rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800" aria-label="Card holders">
                @foreach ($tabs as $key => [$label, $icon])
                    <a
                        href="{{ route('id-cards.index', ['tab' => $key]) }}"
                        @if ($tab === $key) aria-current="page" @endif
                        @class([
                            'flex items-center gap-1.5 rounded-[6px] px-4 py-1.5 text-sm font-semibold transition-colors duration-200',
                            'bg-blue-600 text-white' => $tab === $key,
                            'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' => $tab !== $key,
                        ])
                    ><i class="fa-solid {{ $icon }} text-xs" aria-hidden="true"></i> {{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('id-cards.issued.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"><i class="fa-solid fa-id-card btn-icon" aria-hidden="true"></i> Issued Cards</a>
                <a href="{{ route('id-cards.template-preview') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"><i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i> Preview Template</a>
                <a href="{{ route('id-cards.templates.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"><i class="fa-solid fa-sliders btn-icon" aria-hidden="true"></i> Manage Templates</a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($tab === 'student')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                {{-- One student, by admission number. --}}
                <form method="GET" action="{{ route('id-cards.index') }}" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <input type="hidden" name="tab" value="student">
                    <label for="admission" class="text-sm font-bold text-gray-900 dark:text-white">Find a student by admission number</label>
                    <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">For one student's card. Capital letters don't matter.</small>
                    <div class="mt-3 flex gap-2">
                        <input id="admission" name="admission" type="search" value="{{ $studentSearch }}" placeholder="e.g. {{ $classStudents->first()?->admission_number ?? 'GLA-2026/2027-JUR-001' }}" autocomplete="off" class="min-w-0 flex-1 font-mono">
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-magnifying-glass btn-icon" aria-hidden="true"></i> Find</button>
                    </div>
                </form>

                {{-- A whole class. --}}
                <form method="GET" action="{{ route('id-cards.index') }}" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <input type="hidden" name="tab" value="student">
                    <label for="class" class="text-sm font-bold text-gray-900 dark:text-white">Choose a class</label>
                    <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Every student in it, to generate and print for all of them or for those you tick.</small>
                    <div class="mt-3 flex gap-2">
                        <select id="class" name="class" class="min-w-0 flex-1" onchange="this.form.submit()">
                            <option value="">Select a class…</option>
                            @foreach ($classes as $className)
                                <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Show</button></noscript>
                    </div>
                </form>
            </div>

            @if ($studentSearch !== '')
                @if ($studentNotice)
                    <div class="rounded-[10px] border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300">{{ $studentNotice }}</div>
                @endif

                @if ($studentMatches->isNotEmpty())
                    @include('school-admin.id-cards._people', [
                        'type' => 'student',
                        'people' => $studentMatches,
                        'templates' => $studentTemplates,
                        'title' => $studentMatches->count() === 1 ? $studentMatches->first()->fullName() : 'Admission numbers like '.$studentSearch,
                        'subtitle' => $studentMatches->count() === 1 ? 'Admission number '.$studentMatches->first()->admission_number.' · '.($studentMatches->first()->class_name ?: 'No class') : null,
                        'detailLabel' => 'Class',
                        'detail' => fn ($student) => $student->class_name,
                        'emptyText' => '',
                    ])
                @endif
            @endif

            @if ($selectedClass)
                @include('school-admin.id-cards._people', [
                    'type' => 'student',
                    'people' => $classStudents,
                    'templates' => $studentTemplates,
                    'title' => $selectedClass,
                    'detailLabel' => 'Class',
                    'detail' => fn ($student) => $student->class_name,
                    'emptyText' => 'No active students/pupils in '.$selectedClass.' yet.',
                ])
            @elseif ($studentSearch === '')
                <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <i class="fa-solid fa-id-card-clip text-3xl text-gray-300 dark:text-gray-600" aria-hidden="true"></i>
                    <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Choose a class, or find one student by admission number</p>
                    <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Then generate, preview and print their ID cards.</small>
                </div>
            @endif
        @else
            @php
                $isTeaching = $tab === 'teaching';
                $staffList = $isTeaching ? $teachingStaff : $nonTeachingStaff;
                $departments = $staffDepartments($staffList);
            @endphp

            {{-- One staff member, by Staff ID, from either list. --}}
            <form method="GET" action="{{ route('id-cards.index') }}" class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="staff_id" class="text-sm font-bold text-gray-900 dark:text-white">Find a staff member by Staff ID</label>
                <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Teaching or non-teaching: the right list opens. Capital letters don't matter.</small>
                <div class="mt-3 flex max-w-xl gap-2">
                    <input id="staff_id" name="staff_id" type="search" value="{{ $staffSearch }}" placeholder="e.g. {{ $staffList->first()?->staff_number ?? 'GLA-STAFF-001' }}" autocomplete="off" class="min-w-0 flex-1 font-mono">
                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-magnifying-glass btn-icon" aria-hidden="true"></i> Find</button>
                </div>
            </form>

            @if ($staffSearch !== '')
                @if ($staffNotice)
                    <div @class([
                        'rounded-[10px] border p-4 text-sm',
                        'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300' => $staffMatch,
                        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300' => ! $staffMatch,
                    ])>{{ $staffNotice }}</div>
                @endif

                @if ($staffMatch)
                    @include('school-admin.id-cards._people', [
                        'type' => $isTeaching ? 'teaching_staff' : 'non_teaching_staff',
                        'people' => collect([$staffMatch]),
                        'templates' => $isTeaching ? $teachingStaffTemplates : $nonTeachingStaffTemplates,
                        'title' => $staffMatch->fullName(),
                        'subtitle' => 'Staff ID '.$staffMatch->staff_number.' · '.($isTeaching ? 'Teaching staff' : 'Non-teaching staff').($staffMatch->department ? ' · '.$staffMatch->department : ''),
                        'detailLabel' => 'Department',
                        'detail' => fn ($member) => $member->department,
                        'emptyText' => '',
                    ])
                @endif
            @endif

            <div x-data="{ department: '' }" class="space-y-3">
                @if ($departments->isNotEmpty())
                    <label class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="field-label">Department</span>
                        <select x-model="department" class="w-56">
                            <option value="">All departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @include('school-admin.id-cards._people', [
                    'type' => $isTeaching ? 'teaching_staff' : 'non_teaching_staff',
                    'people' => $staffList,
                    'templates' => $isTeaching ? $teachingStaffTemplates : $nonTeachingStaffTemplates,
                    'title' => $isTeaching ? 'Teaching staff' : 'Non-teaching staff',
                    'detailLabel' => 'Department',
                    'detail' => fn ($member) => $member->department,
                    'rowFilter' => fn ($member) => "department === '' || department === ".\Illuminate\Support\Js::from((string) $member->department),
                    'emptyText' => $isTeaching ? 'No teaching staff yet.' : 'No non-teaching staff yet.',
                ])
            </div>
        @endif
    </div>

    <x-id-card-preview-modal />
</x-dashboard-layout>
