<x-dashboard-layout page-title="Class Subjects" page-subtitle="Choose which subjects each class offers.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <form method="GET" class="max-w-xs">
                <label class="field-label">Class</label>
                <select name="class" onchange="this.form.submit()" class="mt-1 w-full">
                    @foreach ($classOptions as $class)
                        <option value="{{ $class }}" @selected($selectedClass === $class)>{{ $class }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if (! $selectedClass)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No classes yet</p>
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Add a class from the Academics page first.</small>
            </div>
        @else
            <form method="POST" action="{{ route('class-subjects.store') }}" class="space-y-2">
                @csrf
                <input type="hidden" name="class_name" value="{{ $selectedClass }}">

                <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Subjects offered by {{ $selectedClass }}</h2>
                    <small class="field-hint mt-1">Check every subject this class runs. Unchecked subjects won't appear when assigning teachers or creating exams for this class.</small>

                    @if ($jssSubjects->isNotEmpty())
                        <div x-data="{ checked: 0, count() { return $refs.jss.querySelectorAll('input:checked').length } }" x-init="checked = count()" @change="checked = count()" class="mt-4 rounded-[8px] border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-900/50 dark:bg-blue-900/10">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700 dark:text-blue-400">Junior Secondary (JSS) subjects</p>
                                    <small class="block text-xs text-gray-600 dark:text-gray-300"><span x-text="checked">{{ $jssSubjects->filter(fn ($subject) => $offered->contains($subject->name))->count() }}</span> of {{ $jssSubjects->count() }} ticked. These are the default JSS subjects, in result-sheet order.</small>
                                </div>
                                <button type="button" @click="$refs.jss.querySelectorAll('input').forEach(input => input.checked = true); checked = count()" class="btn rounded-[8px] border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:bg-gray-800 dark:text-blue-400"><i class="fa-solid fa-check-double btn-icon" aria-hidden="true"></i> Tick all {{ $jssSubjects->count() }}</button>
                            </div>
                            <div x-ref="jss" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($jssSubjects as $subject)
                                    <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                                        <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked($offered->contains($subject->name)) class="text-blue-600">
                                        <span class="w-6 shrink-0 text-right font-mono text-xs text-gray-400">{{ $loop->iteration }}.</span>
                                        <span class="text-gray-700 dark:text-gray-200">{{ $subject->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 space-y-5">
                        @if ($jssSubjects->isNotEmpty() && $catalogue->isNotEmpty())
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Other subjects</p>
                        @endif
                        @foreach ($categoryOptions as $category)
                            @php $subjects = $catalogue->get($category->value, collect()); @endphp
                            @if ($subjects->isNotEmpty())
                                <div>
                                    <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $category->label() }}</p>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                        @foreach ($subjects as $subject)
                                            <label class="flex items-center gap-2 rounded-[8px] border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                                                <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked($offered->contains($subject->name)) class="text-blue-600">
                                                <span class="text-gray-700 dark:text-gray-200">{{ $subject->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="mt-5">
                        <x-text-field name="custom_subject" label="Add a subject not listed above" placeholder="e.g. Robotics" helper="Optional. Added to the catalogue and offered for this class." />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save Subjects</button>
                </div>
            </form>
        @endif
    </div>
</x-dashboard-layout>
