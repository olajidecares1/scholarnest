@php
    $assignedIds = $examBody->subjects->pluck('id')->all();
@endphp

<x-super-admin-layout :page-title="$examBody->name" :page-subtitle="'Manage subjects and exams for ' . $examBody->name . '.'">
    <div class="space-y-6" x-data="{ addExamOpen: false, editingExam: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <a href="{{ route('super-admin.cbt.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 transition-colors duration-150 hover:text-primary-700 dark:text-primary-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Back to CBT
        </a>

        {{-- Subjects offered --}}
        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Subjects Offered</h2>
            <p class="field-hint mt-1">Choose which subjects {{ $examBody->name }} offers. Only assigned subjects can have exams created for them below.</p>

            <form method="POST" action="{{ route('super-admin.cbt.exam-bodies.subjects.update', $examBody) }}" class="mt-4">
                @csrf
                @method('PUT')

                @foreach (\App\Enums\CbtSubjectCategory::cases() as $category)
                    @php $categorySubjects = $allSubjects->where('category', $category); @endphp
                    @if ($categorySubjects->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $category->label() }}</h3>
                            <div class="mt-2 flex flex-wrap gap-3">
                                @foreach ($categorySubjects as $subject)
                                    <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-1.5 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                        <input
                                            type="checkbox"
                                            name="subjects[]"
                                            value="{{ $subject->id }}"
                                            @checked(in_array($subject->id, $assignedIds, true))
                                            class="text-primary-500"
                                        >
                                        {{ $subject->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                <button type="submit" class="mt-5 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">Save Subjects</button>
            </form>
        </div>

        {{-- Exams --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex items-center justify-between border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Exams</h2>
                    <p class="field-hint mt-1">Each exam is one subject for one year. Add questions once an exam is created.</p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- The way in that was missing. Somebody managing JAMB
                         was looking at this page, and the document uploader
                         was linked only from the All Exam Bodies landing page,
                         so from here there was no upload at all. --}}
                    <a
                        href="{{ route('super-admin.cbt.uploads.index', ['exam_body' => $examBody->uuid]) }}"
                        class="flex items-center gap-2 rounded-[8px] border border-primary-300 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-400"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 16V5m0 0l-4 4m4-4l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5 17.5V19a1.5 1.5 0 001.5 1.5h11A1.5 1.5 0 0019 19v-1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        Upload Document
                    </a>

                    <button
                        type="button"
                        @click="addExamOpen = true"
                        @if ($examBody->subjects->isEmpty()) disabled title="Assign at least one subject first" @endif
                        class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-none"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                        Add Exam
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Subject</th>
                            <th class="px-6 py-3 font-semibold">Year</th>
                            <th class="px-6 py-3 font-semibold">Duration</th>
                            <th class="px-6 py-3 font-semibold">Pass Mark</th>
                            <th class="px-6 py-3 font-semibold">Questions</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($examBody->exams as $exam)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $exam->subject->name }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->year }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->duration_minutes }} mins</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->pass_mark }}%</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $exam->questions_count }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('super-admin.cbt.exams.show', $exam) }}" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage Questions</a>
                                        <button
                                            type="button"
                                            @click="editingExam = { uuid: @js($exam->uuid), title: @js($exam->title()), duration_minutes: @js($exam->duration_minutes), pass_mark: @js($exam->pass_mark) }"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-sm dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('super-admin.cbt.exams.destroy', $exam) }}" onsubmit="return confirm('Delete this exam and all its questions?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-sm dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No exams yet. Click "Add Exam" to create one for a specific subject and year.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Add Exam modal --}}
        <div x-show="addExamOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addExamOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Exam</h3>
                <form method="POST" action="{{ route('super-admin.cbt.exams.store', $examBody) }}" class="mt-4 space-y-2" x-data="{ duration: 60, customDuration: false }">
                    @csrf
                    <x-select-field
                        name="cbt_subject_id"
                        label="Subject"
                        required
                        :options="$examBody->subjects->pluck('name', 'id')->all()"
                    />
                    <x-text-field name="year" label="Year" type="number" icon="M4 20V10M10 20V4M16 20v-7M20 20v-3" helper="Between 1999 and {{ now()->year }}." min="1999" :max="now()->year" value="{{ now()->year }}" required />

                    <div>
                        <label class="field-label mb-1">Duration</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="preset in [60, 70, 80, 90, 100]" :key="preset">
                                <button
                                    type="button"
                                    @click="duration = preset; customDuration = false"
                                    :class="(!customDuration && duration === preset) ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-900/30 dark:text-primary-400' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                                    class="rounded-[8px] border px-3 py-2 text-sm font-semibold transition-colors duration-150"
                                >
                                    <span x-text="preset"></span> min
                                </button>
                            </template>
                            <button
                                type="button"
                                @click="customDuration = true"
                                :class="customDuration ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-900/30 dark:text-primary-400' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700'"
                                class="rounded-[8px] border px-3 py-2 text-sm font-semibold transition-colors duration-150"
                            >
                                Custom
                            </button>
                        </div>
                        <input
                            x-show="customDuration"
                            type="number"
                            x-model.number="duration"
                            min="5"
                            max="300"
                            placeholder="Minutes"
                            class="mt-2 w-full transition-colors duration-200"
                        >
                        <input type="hidden" name="duration_minutes" :value="duration">
                    </div>

                    <x-text-field name="pass_mark" label="Pass Mark (%)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0" max="100" value="50" required />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addExamOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Exam modal (duration/pass mark only) --}}
        <div x-show="editingExam" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="editingExam = null" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editingExam ? `Edit ${editingExam.title}` : ''"></h3>
                <template x-if="editingExam">
                    <form
                        method="POST"
                        :action="'{{ route('super-admin.cbt.exams.update', ['exam' => '__ID__']) }}'.replace('__ID__', editingExam.uuid)"
                        class="mt-4 space-y-2"
                    >
                        @csrf @method('PUT')
                        <x-text-field name="duration_minutes" label="Duration (minutes)" type="number" icon="M12 21a9 9 0 100-18 9 9 0 000 18z" min="5" max="300" x-model.number="editingExam.duration_minutes" required />
                        <x-text-field name="pass_mark" label="Pass Mark (%)" type="number" icon="M8 12.3l2.6 2.6L16.3 9" min="0" max="100" x-model.number="editingExam.pass_mark" required />
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editingExam = null" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                            <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save Changes</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
</x-super-admin-layout>
