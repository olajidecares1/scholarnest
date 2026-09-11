{{-- Class Note: one Word document, sent to as many classes as are ticked.

     The class list is checkboxes rather than a multi-select, because a
     multi-select on a phone is a scrolling list with invisible state - and
     this page is used on a phone. The server re-checks every ticked class
     against the school's own list; this is the convenience, not the
     boundary. --}}
<x-staff-layout page-title="Class Note" page-subtitle="Send a Word document to one or more of your classes.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[8px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        @unless ($hasStudentPortal)
            {{-- Basic. The note is stored and kept, but there is no student
                 portal for it to arrive in, and saying so here is better than
                 letting a teacher upload into silence. --}}
            <div class="flex items-start gap-3 rounded-[10px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-900/20">
                <i class="fa-solid fa-circle-info mt-0.5 text-amber-600 dark:text-amber-400"></i>
                <div>
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-300">Your plan has no student portal</h2>
                    <p class="mt-1 text-[12.5px] leading-[1.6] text-amber-800 dark:text-amber-400">
                        You can keep class notes here, but students cannot sign in to receive them on your current
                        plan. Ask your school about upgrading if you would like them delivered to students.
                    </p>
                </div>
            </div>
        @endunless

        @if (empty($classOptions))
            {{-- Not an empty checkbox list. A school with no classes set up
                 cannot send a note, and only the school office can fix it. --}}
            <div class="flex items-start gap-3 rounded-[10px] border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-900/20">
                <i class="fa-solid fa-folder-open mt-0.5 text-amber-600 dark:text-amber-400"></i>
                <div>
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-300">No classes set up yet</h2>
                    <p class="mt-1 text-[12.5px] leading-[1.6] text-amber-800 dark:text-amber-400">
                        A class note is sent to a class. Ask your school office to set up classes, and they will
                        appear here to choose from.
                    </p>
                </div>
            </div>
        @else
            <div class="rounded-[10px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <i class="fa-solid fa-file-word text-primary-500"></i>
                    Send a class note
                </h2>
                <p class="field-hint mt-0.5">
                    Upload the document once and tick every class that should receive it &mdash; there is no need to
                    send it again for each class.
                </p>

                <form method="POST" action="{{ route('staff.class-notes.store', $school) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div>
                            <label for="title" class="field-label">Note Title</label>
                            <input id="title" name="title" type="text" required maxlength="150" value="{{ old('title') }}" placeholder="e.g. Photosynthesis — Week 4" class="mt-1">
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>

                        <div>
                            <label for="subject" class="field-label">Subject <span class="font-normal text-gray-400">(optional)</span></label>
                            @if ($subjects->isNotEmpty())
                                <input id="subject" name="subject" type="text" list="class-note-subjects" maxlength="100" value="{{ old('subject') }}" placeholder="Choose or type a subject" class="mt-1">
                                <datalist id="class-note-subjects">
                                    @foreach ($subjects as $subjectOption)
                                        <option value="{{ $subjectOption }}"></option>
                                    @endforeach
                                </datalist>
                            @else
                                <input id="subject" name="subject" type="text" maxlength="100" value="{{ old('subject') }}" placeholder="e.g. Biology" class="mt-1">
                            @endif
                            <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label for="description" class="field-label">Instructions <span class="font-normal text-gray-400">(optional)</span></label>
                        <textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Anything the class should know before they read it." class="mt-1">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    <div>
                        <label for="document" class="field-label">Word Document</label>
                        <input id="document" name="document" type="file" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required class="mt-1">
                        <small class="field-hint block">
                            Microsoft Word only (.doc or .docx), up to {{ $maxUploadLabel }}.
                            A .docx also shows its text in the student's portal so they can read and copy it without Word.
                        </small>
                        <x-input-error :messages="$errors->get('document')" class="mt-1" />
                    </div>

                    {{-- The classes. Ticked, not selected: one tap each, and
                         every choice stays visible on a small screen. --}}
                    <div x-data="{
                        get boxes() { return Array.from($refs.classes.querySelectorAll('input[type=checkbox]')) },
                        get chosen() { return this.boxes.filter((box) => box.checked).length },
                        all(state) { this.boxes.forEach((box) => { box.checked = state }) },
                    }">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="field-label">Send to these classes</label>
                            <div class="flex items-center gap-3 text-[11.5px] font-bold">
                                <button type="button" x-on:click="all(true)" class="text-primary-600 hover:text-primary-700 dark:text-primary-400">Select all</button>
                                <button type="button" x-on:click="all(false)" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">Clear</button>
                            </div>
                        </div>

                        <div x-ref="classes" class="mt-1.5 grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($classOptions as $className)
                                @php($roll = (int) ($rollCall[$className] ?? 0))
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-[8px] border border-gray-200 p-2.5 transition hover:border-primary-300 hover:bg-primary-50/40 has-[:checked]:border-primary-400 has-[:checked]:bg-primary-50/60 dark:border-gray-700 dark:hover:bg-gray-700/40 dark:has-[:checked]:bg-primary-900/20">
                                    <input
                                        type="checkbox"
                                        name="class_names[]"
                                        value="{{ $className }}"
                                        @checked(in_array($className, old('class_names', []), true))
                                        class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12.5px] font-bold text-gray-900 dark:text-gray-100">{{ $className }}</span>
                                        <small class="block text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $roll === 0 ? 'No students yet' : $roll.' student'.($roll === 1 ? '' : 's') }}
                                            @if ($myClassNames->contains($className))
                                                &middot; <span class="font-semibold text-primary-600 dark:text-primary-400">You teach this</span>
                                            @endif
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <x-input-error :messages="$errors->get('class_names')" class="mt-1" />
                        <x-input-error :messages="$errors->get('class_names.*')" class="mt-1" />

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <small class="field-hint" x-text="chosen === 0 ? 'No classes chosen yet.' : chosen + (chosen === 1 ? ' class selected.' : ' classes selected.')"></small>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-700">
                                <i class="fa-solid fa-paper-plane text-[12px]"></i>
                                Send Class Note
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        {{-- What this member has already sent. --}}
        <div>
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Notes you have sent</h2>

            <div class="mt-3 space-y-3">
                @forelse ($notes as $note)
                    <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $note->title }}</p>
                                <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                    @if ($note->subject){{ $note->subject }} &middot; @endif
                                    {{ $note->created_at->format('j M Y') }} &middot; {{ $note->readableSize() }}
                                </p>
                            </div>
                            <span class="shrink-0 text-[11px] text-gray-400">{{ $note->created_at->diffForHumans() }}</span>
                        </div>

                        @if ($note->description)
                            <p class="mt-2 text-[12.5px] leading-[1.6] text-gray-600 dark:text-gray-300">{{ $note->description }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($note->classNames() as $className)
                                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $className }}</span>
                            @endforeach
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                            <a href="{{ route('staff.class-notes.download', [$school, $note]) }}" class="inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-[12px] font-bold text-gray-700 transition hover:border-primary-400 hover:text-primary-700 dark:border-gray-600 dark:text-gray-200">
                                <i class="fa-solid fa-download text-[11px]"></i>
                                Download
                            </a>

                            <form method="POST" action="{{ route('staff.class-notes.destroy', [$school, $note]) }}" onsubmit="return confirm('Withdraw “{{ $note->title }}”? Students will no longer be able to open it.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-[12px] font-bold text-red-600 transition hover:border-red-300 dark:border-gray-600">
                                    <i class="fa-solid fa-xmark text-[11px]"></i>
                                    Withdraw
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                        <i class="fa-solid fa-file-word text-2xl text-gray-300"></i>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">You have not sent any class notes yet.</p>
                    </div>
                @endforelse

                @if ($notes->hasPages())
                    <div class="pt-2">{{ $notes->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-staff-layout>
