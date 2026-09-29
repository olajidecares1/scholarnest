@props(['notices' => [], 'preview' => false])

{{--
    Shown when a registration is refused because the student/pupil is already
    registered. Deliberately loud: it is the only thing that tells the School
    Admin nothing was saved, and it carries the existing record's admission
    number and details so they can check it is the same child without leaving
    the page.
--}}
@if (count($notices))
    <section
        {{ $attributes->merge(['class' => 'scroll-mt-28 overflow-hidden rounded-[10px] border-2 focus:outline-none border-amber-400 bg-amber-50 shadow-md dark:border-amber-500/70 dark:bg-amber-900/20']) }}
        role="alert"
        aria-live="assertive"
        tabindex="-1"
        @unless ($preview)
            x-data
            x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' }); $el.focus({ preventScroll: true })"
        @endunless
    >
        <header class="flex items-start gap-3 border-b border-amber-300 bg-amber-100 px-5 py-4 dark:border-amber-500/40 dark:bg-amber-900/40">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
            </span>
            <div>
                <h2 class="text-base font-extrabold text-amber-900 dark:text-amber-200">
                    @if ($preview)
                        {{ count($notices) }} {{ count($notices) === 1 ? 'student/pupil in this file is' : 'students/pupils in this file are' }} already registered and will not be saved again.
                    @elseif (count($notices) === 1)
                        Student already registered. The new registration was not saved.
                    @else
                        {{ count($notices) }} students/pupils are already registered and were not saved again.
                    @endif
                </h2>
                <small class="mt-0.5 block text-sm text-amber-800 dark:text-amber-300">
                    A student/pupil with the same name is already on your school's records, so no second record {{ $preview ? 'will be' : 'was' }} created. Review the existing {{ count($notices) === 1 ? 'record' : 'records' }} below.
                </small>
            </div>
        </header>

        <div class="divide-y divide-amber-200 dark:divide-amber-500/30">
            @foreach ($notices as $notice)
                <article class="px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $notice['full_name'] }}</p>
                            <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
                                Admission Number:
                                <span class="rounded-[6px] bg-white px-2 py-0.5 font-mono text-sm font-bold text-gray-900 ring-1 ring-amber-300 dark:bg-gray-900 dark:text-white dark:ring-amber-500/50">{{ $notice['admission_number'] }}</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold',
                                'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300' => $notice['is_bulk'] === true,
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' => $notice['is_bulk'] === false,
                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $notice['is_bulk'] === null,
                            ])>
                                <i @class(['fa-solid', 'fa-layer-group' => $notice['is_bulk'] === true, 'fa-user-plus' => $notice['is_bulk'] === false, 'fa-circle-question' => $notice['is_bulk'] === null]) aria-hidden="true"></i>
                                {{ $notice['registration_source'] }}
                            </span>

                            <a href="{{ $notice['show_url'] }}" class="btn inline-flex items-center rounded-[8px] bg-amber-600 font-semibold text-white hover:bg-amber-700">
                                <i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i> View existing record
                            </a>
                        </div>
                    </div>

                    <small class="mt-2 block text-sm text-amber-900 dark:text-amber-200">{{ \Illuminate\Support\Str::after($notice['reason'], 'Already registered: ') }}</small>

                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 rounded-[8px] bg-white p-4 text-sm ring-1 ring-amber-200 dark:bg-gray-800 dark:ring-amber-500/30 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($notice['details'] as $label => $value)
                            <div class="min-w-0">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="break-words font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    </section>
@endif
