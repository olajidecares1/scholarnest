<x-super-admin-layout page-title="CBT" page-subtitle="Manage computer-based test exam bodies, subjects, and question banks.">
    <div class="space-y-6" x-data="{ tab: 'exam-bodies', addExamBodyOpen: false, editingExamBody: null, addSubjectOpen: false, editingSubject: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('super-admin.cbt.uploads.index') }}" class="flex items-center gap-2 rounded-[8px] border border-primary-300 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4v12M7 9l5-5 5 5M5 20h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Import from Document
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Exam Bodies</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($examBodies->count()) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Subjects</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($subjects->count()) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Questions</p>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($totalQuestions) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
            <button type="button" @click="tab = 'exam-bodies'" class="rounded-t-[5px] border-b-2 px-4 py-3 text-sm font-semibold transition-colors duration-200" :class="tab === 'exam-bodies' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">Exam Bodies</button>
            <button type="button" @click="tab = 'subjects'" class="rounded-t-[5px] border-b-2 px-4 py-3 text-sm font-semibold transition-colors duration-200" :class="tab === 'subjects' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">Subjects</button>
        </div>

        {{-- Exam Bodies tab --}}
        <div x-show="tab === 'exam-bodies'">
            <div class="flex justify-end">
                <button type="button" @click="editingExamBody = { name: '', code: '', description: '', academic_stages: [] }; addExamBodyOpen = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Exam Body
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($examBodies as $examBody)
                    <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                        <div class="flex items-start justify-between gap-2">
                            <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">{{ $examBody->code }}</span>
                            <button type="button" @click="editingExamBody = { uuid: @js($examBody->uuid), name: @js($examBody->name), code: @js($examBody->code), description: @js($examBody->description), academic_stages: @js($examBody->academic_stages ?? []) }; addExamBodyOpen = true" class="text-gray-400 transition-colors duration-150 hover:text-primary-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20l4.3-.7L19 8.6a1.5 1.5 0 000-2.1l-1.5-1.5a1.5 1.5 0 00-2.1 0L4.7 15.7 4 20z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                        </div>
                        <h3 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">{{ $examBody->name }}</h3>
                        @if ($examBody->description)
                            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $examBody->description }}</p>
                        @endif
                        @if (! empty($examBody->academic_stages))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach ($examBody->academic_stages as $stage)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ \App\Enums\AcademicStage::from($stage)->label() }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-4 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $examBody->subjects_count }} subjects</span>
                            <span>{{ $examBody->exams_count }} exams</span>
                            <span>{{ $examBody->questions_count }} questions</span>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <a href="{{ route('super-admin.cbt.exam-bodies.show', $examBody) }}" class="flex-1 rounded-[8px] bg-primary-50 px-3 py-2 text-center text-xs font-semibold text-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-400">Manage</a>
                            <form method="POST" action="{{ route('super-admin.cbt.exam-bodies.destroy', $examBody) }}" onsubmit="return confirm('Delete {{ $examBody->name }}? This removes all its subjects, exams, and questions.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400 lg:rounded-[10px]">
                        No exam bodies yet. Click "Add Exam Body" to create WAEC, BECE, JAMB, NECO, or any other CBT category.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Subjects tab --}}
        <div x-show="tab === 'subjects'" style="display: none;">
            <div class="flex justify-end">
                <button type="button" @click="editingSubject = { name: '', category: 'general' }; addSubjectOpen = true" class="flex items-center gap-2 rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-600 hover:shadow-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Subject
                </button>
            </div>

            @foreach (\App\Enums\CbtSubjectCategory::cases() as $category)
                @php $categorySubjects = $subjects->where('category', $category); @endphp
                @if ($categorySubjects->isNotEmpty())
                    <div class="mt-5">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $category->label() }}</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($categorySubjects as $subject)
                                <span class="group inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1.5 pl-3 pr-1.5 text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    {{ $subject->name }}
                                    <button type="button" @click="editingSubject = { uuid: @js($subject->uuid), name: @js($subject->name), category: @js($subject->category->value) }; addSubjectOpen = true" class="rounded-full p-1 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-gray-700">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20l4.3-.7L19 8.6a1.5 1.5 0 000-2.1l-1.5-1.5a1.5 1.5 0 00-2.1 0L4.7 15.7 4 20z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </button>
                                    <form method="POST" action="{{ route('super-admin.cbt.subjects.destroy', $subject) }}" onsubmit="return confirm('Delete {{ $subject->name }}? This removes it from every exam body and any exams already created for it.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-full p-1 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" /></svg>
                                        </button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Add/Edit Exam Body modal --}}
        <div x-show="addExamBodyOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addExamBodyOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editingExamBody?.uuid ? 'Edit Exam Body' : 'Add Exam Body'"></h3>
                <form method="POST" :action="editingExamBody?.uuid ? '{{ route('super-admin.cbt.exam-bodies.update', ['examBody' => '__ID__']) }}'.replace('__ID__', editingExamBody.uuid) : '{{ route('super-admin.cbt.exam-bodies.store') }}'" class="mt-4 space-y-2">
                    @csrf
                    <template x-if="editingExamBody?.uuid"><input type="hidden" name="_method" value="PUT"></template>
                    <x-text-field name="name" label="Name" icon="M12 3l8 3.6v2L12 12 4 8.6v-2L12 3z" helper="e.g. West African Examinations Council." x-model="editingExamBody.name" required />
                    <x-text-field name="code" label="Short Code" icon="M7 8h10M7 12h10M7 16h6" helper="A short unique code shown in the sidebar, e.g. WAEC." x-model="editingExamBody.code" required />
                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" helper="Optional context shown to other admins." rows="3" x-model="editingExamBody.description" />

                    <div>
                        <label class="field-label mb-1">Academic Stages</label>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Which student levels can see this exam body in their portal.</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach (\App\Enums\AcademicStage::cases() as $stage)
                                <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-1.5 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                    <input
                                        type="checkbox"
                                        name="academic_stages[]"
                                        value="{{ $stage->value }}"
                                        x-model="editingExamBody.academic_stages"
                                        class="text-primary-500"
                                    >
                                    {{ $stage->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addExamBodyOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Add/Edit Subject modal --}}
        <div x-show="addSubjectOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addSubjectOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editingSubject ? 'Edit Subject' : 'Add Subject'"></h3>
                <form method="POST" :action="editingSubject ? '{{ route('super-admin.cbt.subjects.update', ['subject' => '__ID__']) }}'.replace('__ID__', editingSubject.uuid) : '{{ route('super-admin.cbt.subjects.store') }}'" class="mt-4 space-y-2">
                    @csrf
                    <template x-if="editingSubject"><input type="hidden" name="_method" value="PUT"></template>
                    <x-text-field name="name" label="Subject Name" icon="M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11" x-model="editingSubject ? editingSubject.name : ''" required />
                    <x-select-field
                        name="category"
                        label="Category"
                        required
                        model="editingSubject.category"
                        :options="collect(\App\Enums\CbtSubjectCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                    />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addSubjectOpen = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-super-admin-layout>
