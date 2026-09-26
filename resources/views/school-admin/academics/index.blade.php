@php
    use App\Enums\ClassStream;
    use Illuminate\Support\Str;

    $levelIcon = function (string $name): array {
        $name = Str::lower($name);

        return match (true) {
            Str::contains($name, ['creche', 'kg', 'kinder']) => [
                'path' => 'M12 3l2.6 5.6 6.1.6-4.6 4.1 1.3 6-5.4-3.2-5.4 3.2 1.3-6-4.6-4.1 6.1-.6z',
                'extra' => '',
            ],
            Str::contains($name, 'nursery') => [
                'path' => 'M12 21v-6.5M12 14.5C7.5 14.5 5 11.8 5 7.5c4.5 0 7 2.7 7 7z',
                'extra' => '<path d="M12 14.5c4.5 0 7-2.7 7-7-4.5 0-7 2.7-7 7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />',
            ],
            Str::contains($name, 'primary') => [
                'path' => 'M4 5.7c2.3-1.1 5.4-1.1 8 0M12 5.7c2.6-1.1 5.7-1.1 8 0v12.6c-2.3-1.1-5.4-1.1-8 0M12 5.7v12.6M4 5.7v12.6c2.3-1.1 5.4-1.1 8 0',
                'extra' => '',
            ],
            Str::contains($name, ['jss', 'junior']) => [
                'path' => 'M8 7.5V6a4 4 0 118 0v1.5M6 8h12a1 1 0 011 1v10a1 1 0 01-1 1H6a1 1 0 01-1-1V9a1 1 0 011-1z',
                'extra' => '<path d="M9.5 8v3.5h5V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />',
            ],
            default => [
                'path' => 'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z',
                'extra' => '<path d="M6.5 11v4c0 1.4 2.5 2.75 5.5 2.75s5.5-1.35 5.5-2.75v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" /><path d="M20.5 9v5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />',
            ],
        };
    };

    $streamAccent = fn (?ClassStream $stream) => match ($stream) {
        ClassStream::Science => ['label' => 'Science', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500'],
        ClassStream::Art => ['label' => 'Art', 'text' => 'text-purple-700 dark:text-purple-400', 'dot' => 'bg-purple-500'],
        ClassStream::Commercial => ['label' => 'Commercial', 'text' => 'text-amber-700 dark:text-amber-400', 'dot' => 'bg-amber-500'],
        default => ['label' => 'Ungrouped', 'text' => 'text-gray-500 dark:text-gray-400', 'dot' => 'bg-gray-400'],
    };
@endphp

<x-dashboard-layout page-title="Academics" page-subtitle="Every class your school runs, organized by level.">
    <div class="space-y-8" x-data="{ addLevelOpen: false }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        {{-- Academic Excellence is its own card on the public website, so it
             has its own background, separate from the one News and Events
             share. --}}
        <x-card-background-field
            :action="route('website.academics-card-background')"
            :current="$website?->academicsCardImageUrl()"
            title="Academic Excellence card background"
            description="Sits behind the Academic Excellence section of your public website. Leave it empty for the plain background."
        />

        <div class="flex justify-end">
            <button
                type="button"
                @click="addLevelOpen = true"
                class="btn flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <i class="fa-solid fa-plus text-[14px] leading-none" aria-hidden="true"></i>
                Add Academic Level
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Term Dates</h2>
            <small class="field-hint mt-1">Used to work out "this term's" attendance on report cards and in the Teacher Portal.</small>

            @if ($terms->isNotEmpty())
                <div class="mt-4 overflow-x-auto rounded-[8px] border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Session</th>
                                <th class="px-3 py-2 font-semibold">Term</th>
                                <th class="px-3 py-2 font-semibold">Starts</th>
                                <th class="px-3 py-2 font-semibold">Ends</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($terms as $term)
                                <tr>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $term->session }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $term->term->label() }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $term->starts_on->format('M j, Y') }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $term->ends_on->format('M j, Y') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('academics.terms.destroy', $term) }}" onsubmit="return confirm('Remove these term dates?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('academics.terms.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end">
                @csrf
                <x-select-field name="session" label="Session" :options="collect($sessionOptions)->mapWithKeys(fn ($s) => [$s => $s])->all()" :selected="\App\Support\AcademicSession::current()" />
                <x-select-field name="term" label="Term" :options="collect($termOptions)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                <x-text-field name="starts_on" label="Starts On" type="date" required />
                <x-text-field name="ends_on" label="Ends On" type="date" required />
                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Save</button>
            </form>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Grading</h2>
            <small class="field-hint mt-1">
                Configure the percentage ranges, letters, and descriptions used to grade every score in this school.
                Leave it empty to use the default A to E scale; add even one grade and this school is graded on your scale alone.
            </small>

            @if ($gradeCoverageGaps !== [])
                {{-- Once a school has its own scale, nothing falls back to the
                     built-in one, so an uncovered range would print a dash on a
                     report card instead of a grade. Said here, where it can be
                     fixed, rather than there. --}}
                <div class="mt-4 flex items-start gap-2.5 rounded-[8px] border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                    <p class="text-xs leading-[1.6] text-amber-900 dark:text-amber-300">
                        Your scale does not cover
                        @foreach ($gradeCoverageGaps as $gap)<strong>{{ $gap['from'] }} to {{ $gap['to'] }}%</strong>@if (! $loop->last), @endif @endforeach.
                        A score in that range will show no grade. Add a band covering it.
                    </p>
                </div>
            @endif

            @if ($gradeBands->isNotEmpty())
                <div class="mt-4 overflow-x-auto rounded-[8px] border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Min %</th>
                                <th class="px-3 py-2 font-semibold">Max %</th>
                                <th class="px-3 py-2 font-semibold">Letter</th>
                                <th class="px-3 py-2 font-semibold">Description</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($gradeBands as $band)
                                <tr x-data="{ editing: false }">
                                    <template x-if="!editing">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $band->min_percent }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $band->max_percent }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ $band->letter }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $band->description ?: 'N/A' }}</td>
                                    </template>
                                    <template x-if="!editing">
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" @click="editing = true" class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400">Edit</button>
                                            <form method="POST" action="{{ route('academics.grade-bands.destroy', $band) }}" onsubmit="return confirm('Remove grade {{ $band->letter }}?');" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="ml-2 text-xs font-semibold text-red-600 hover:text-red-700">Remove</button>
                                            </form>
                                        </td>
                                    </template>

                                    <template x-if="editing">
                                        <td colspan="5" class="px-3 py-2">
                                            <form method="POST" action="{{ route('academics.grade-bands.update', $band) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-5 sm:items-end">
                                                @csrf @method('PUT')
                                                <input type="number" name="min_percent" value="{{ $band->min_percent }}" min="0" max="100" required  placeholder="Min %">
                                                <input type="number" name="max_percent" value="{{ $band->max_percent }}" min="0" max="100" required  placeholder="Max %">
                                                <input type="text" name="letter" value="{{ $band->letter }}" maxlength="3" required  placeholder="Letter">
                                                <input type="text" name="description" value="{{ $band->description }}" maxlength="100"  placeholder="Description">
                                                <div class="flex gap-2">
                                                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
                                                    <button type="button" @click="editing = false" class="btn rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                                                </div>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('academics.grade-bands.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-5 sm:items-end">
                @csrf
                <x-text-field name="min_percent" label="Min %" type="number" min="0" max="100" required />
                <x-text-field name="max_percent" label="Max %" type="number" min="0" max="100" required />
                <x-text-field name="letter" label="Letter" maxlength="3" placeholder="e.g. A" required />
                <x-text-field name="description" label="Description" placeholder="e.g. Excellent" />
                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Add Grade</button>
            </form>
        </div>

        @forelse ($levels as $level)
            @php
                $icon = $levelIcon($level->name);
                $streamGroups = $level->classes->groupBy(fn ($class) => $class->stream?->value);
                $isStreamed = $streamGroups->keys()->filter()->isNotEmpty();
            @endphp

            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]" x-data="{ editingLevel: false, levelName: @js($level->name), levelCode: @js($level->code) }">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                {!! $icon['extra'] !!}
                                <path d="{{ $icon['path'] }}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <template x-if="!editingLevel">
                            <div class="flex min-w-0 items-center gap-2">
                                <h2 class="truncate text-sm font-bold text-gray-900 dark:text-white" x-text="levelName"></h2>
                                <span x-show="levelCode" x-text="levelCode" class="shrink-0 rounded-[6px] bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400"></span>
                            </div>
                        </template>
                        <form x-show="editingLevel" method="POST" action="{{ route('academics.levels.update', $level) }}" class="flex flex-1 items-center gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="name" x-model="levelName" class="w-full">
                            <input type="text" name="code" x-model="levelCode" maxlength="10" placeholder="Code" title="Admission-number level code, e.g. PRY" class="w-20 shrink-0 uppercase">
                            <button type="submit" class="btn shrink-0 rounded-[8px] bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700">Save</button>
                        </form>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" @click="editingLevel = !editingLevel" class="rounded-[8px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-blue-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-blue-400">
                            <i class="fa-solid fa-pen text-[14px] leading-none" aria-hidden="true"></i>
                        </button>
                        <form method="POST" action="{{ route('academics.levels.destroy', $level) }}" onsubmit="return confirm('Delete {{ $level->name }} and every class inside it?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-[8px] p-1.5 text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:text-gray-500 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                                <i class="fa-solid fa-xmark text-[14px] leading-none" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-5 space-y-5">
                    @if ($isStreamed)
                        @foreach ([ClassStream::Science, ClassStream::Art, ClassStream::Commercial, null] as $stream)
                            @php $classes = $streamGroups->get($stream?->value ?? '', collect()); @endphp
                            @if ($classes->isNotEmpty() || $stream !== null)
                                @php $accent = $streamAccent($stream); @endphp
                                <div>
                                    <p class="mb-2 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wide {{ $accent['text'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $accent['dot'] }}"></span>
                                        {{ $accent['label'] }}
                                    </p>
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                        @foreach ($classes as $class)
                                            @include('school-admin.academics._class-card', ['class' => $class])
                                        @endforeach
                                        @include('school-admin.academics._add-class-card', ['level' => $level, 'stream' => $stream])
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                            @foreach ($level->classes as $class)
                                @include('school-admin.academics._class-card', ['class' => $class])
                            @endforeach
                            @include('school-admin.academics._add-class-card', ['level' => $level])
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400 lg:rounded-[10px]">
                No academic levels yet. Click "Add Academic Level" to get started.
            </div>
        @endforelse

        {{-- Add Level modal --}}
        <div x-show="addLevelOpen" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="addLevelOpen = false" class="w-full max-w-md rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Add Academic Level</h3>
                <small class="field-hint mt-1">e.g. Senior Secondary School, Junior Secondary School, Upper Primary, Lower Primary, Nursery, Kindergarten/Creche, or any other level your school uses.</small>
                <form method="POST" action="{{ route('academics.levels.store') }}" class="mt-4 space-y-2">
                    @csrf
                    <input type="text" name="name" required placeholder="Level name" class="w-full">
                    <div>
                        <input type="text" name="code" maxlength="10" placeholder="Code (optional, e.g. PRY)" class="w-full uppercase">
                        <small class="field-hint mt-1">Used in auto-generated admission numbers for classes in this level, e.g. "PRY" &rarr; MIS-2025/2026-PRY-004.</small>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addLevelOpen = false" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
