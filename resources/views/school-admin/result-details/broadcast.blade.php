{{-- WhatsApp broadcast of result details. See ResultBroadcastController. --}}
@php
    $card = 'rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    $count = $students->count();
@endphp

<x-dashboard-layout page-title="WhatsApp broadcast" page-subtitle="{{ $examination->class_name }} · {{ $examination->term->label() }} {{ $examination->session }}">
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('results.index', ['class' => $examination->class_name, 'session' => $examination->session, 'term' => $examination->term->value]) }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                <i class="fa-solid fa-chevron-left text-[12px]" aria-hidden="true"></i> Results
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @unless ($resultLinkLive)
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                Your result-checking link is switched off, so the links would not open. Switch it on under Exam Tokens first.
            </div>
        @endunless

        {{-- Who gets what. --}}
        <div class="{{ $card }}">
            <h2 class="font-bold text-gray-900 dark:text-white">
                {{ $chosen === [] ? 'The whole of '.$examination->class_name : $count.' selected '.Str::plural('pupil', $count) }}
            </h2>
            <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-[8px] bg-gray-50 p-3 dark:bg-gray-900/40">
                    <span class="block text-2xl font-bold text-gray-900 dark:text-white">{{ $count }}</span>
                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ Str::plural('pupil', $count) }}</span>
                </div>
                <div class="rounded-[8px] bg-green-50 p-3 dark:bg-green-900/20">
                    <span class="block text-2xl font-bold text-green-700 dark:text-green-400">{{ $messages }}</span>
                    <span class="text-xs text-gray-700 dark:text-gray-300">WhatsApp {{ Str::plural('message', $messages) }}</span>
                </div>
                <div class="rounded-[8px] p-3 {{ $missing->isNotEmpty() ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-900/40' }}">
                    <span class="block text-2xl font-bold {{ $missing->isNotEmpty() ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-white' }}">{{ $missing->count() }}</span>
                    <span class="text-xs text-gray-700 dark:text-gray-300">no parent number</span>
                </div>
            </div>
            <ul class="mt-4 space-y-1.5 text-sm text-gray-700 dark:text-gray-200">
                <li><i class="fa-solid fa-check mr-1.5 text-green-600" aria-hidden="true"></i>Each parent gets <strong>only their own child's</strong> name, class, admission number, exam token and result link.</li>
                <li><i class="fa-solid fa-check mr-1.5 text-green-600" aria-hidden="true"></i>Numbers come from each pupil's own record. Only tokens you generated under Exam Tokens are sent.</li>
                <li><i class="fa-solid fa-check mr-1.5 text-green-600" aria-hidden="true"></i>The link opens the result straight away: no portal, nothing to type.</li>
            </ul>

            @if ($noToken->isNotEmpty())
                <div class="mt-3 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                    <p class="font-semibold">{{ $noToken->count() }} {{ Str::plural('pupil', $noToken->count()) }} left out: no exam token they can use</p>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        @foreach ($noToken as $pupil)
                            <li>{{ $pupil['name'] }} ({{ $pupil['reason'] }})</li>
                        @endforeach
                    </ul>
                    <a href="{{ route('result-pins.index') }}" class="mt-2 inline-block text-xs font-semibold underline">Generate tokens under Exam Tokens</a>, then come back.
                </div>
            @endif

            @if ($missing->isNotEmpty())
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer font-semibold text-amber-800 dark:text-amber-300">
                        {{ $missing->count() }} {{ Str::plural('pupil', $missing->count()) }} with no parent WhatsApp number on record
                    </summary>
                    <ul class="mt-2 space-y-1">
                        @foreach ($missing as $pupil)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-gray-800 dark:text-gray-100">{{ $pupil->fullName() }}</span>
                                <a href="{{ route('students.show', $pupil) }}" class="text-xs font-semibold text-primary-700 hover:underline dark:text-primary-300">Add contact</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        {{-- What one parent receives. --}}
        @if ($sample)
            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">What each parent receives (example)</h2>
                <div class="mt-3 rounded-[10px] rounded-tl-none bg-[#d9fdd3] p-3 text-sm leading-relaxed text-gray-900 shadow-sm dark:bg-[#005c4b] dark:text-white">
                    <p class="whitespace-pre-line break-words">{{ $sample }}</p>
                </div>
                <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">The token is hidden here; each parent sees their own child's.</p>
                <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">
                    Result link: your result-checking link from Settings,
                    <span class="break-all font-mono">{{ $school->resultLinkUrl() }}</span>,
                    which opens the child's result directly.
                </p>
            </div>
        @endif

        {{-- The one action. --}}
        <form method="POST" action="{{ route('result-pins.details.send') }}" class="{{ $card }}">
            @csrf
            <input type="hidden" name="channel" value="whatsapp">
            <input type="hidden" name="examination" value="{{ $examination->uuid }}">
            @if ($chosen === [])
                <input type="hidden" name="all" value="1">
            @else
                @foreach ($chosen as $uuid)
                    <input type="hidden" name="students[]" value="{{ $uuid }}">
                @endforeach
            @endif

            <button type="submit" @disabled($messages === 0) class="btn flex w-full items-center justify-center gap-2 rounded-[10px] bg-[#25D366] px-4 py-3 text-base font-bold text-white hover:bg-[#1ebe5b] disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fa-brands fa-whatsapp text-xl" aria-hidden="true"></i>
                Send to {{ $messages }} {{ Str::plural('parent', $messages) }} on WhatsApp
            </button>

            @if ($messages === 0)
                {{-- Say why there is nobody to send to, and what to do. --}}
                <div class="mt-3 rounded-[8px] border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                    <p class="font-semibold">No parent can be sent to yet.</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                        @if ($noToken->isNotEmpty())
                            <li>
                                {{ $noToken->count() }} of {{ $count }} {{ Str::plural('pupil', $count) }} {{ $noToken->count() === 1 ? 'has' : 'have' }} no exam token for this result.
                                <a href="{{ route('result-pins.index') }}" class="font-semibold underline">Generate tokens under Exam Tokens</a>, then come back.
                            </li>
                        @endif
                        @if ($missing->isNotEmpty())
                            <li>{{ $missing->count() }} {{ Str::plural('pupil', $missing->count()) }} {{ $missing->count() === 1 ? 'has' : 'have' }} no parent WhatsApp number on record: add it with <strong>Add contact</strong> above.</li>
                        @endif
                        @if ($count === 0)
                            <li>There are no pupils in this class.</li>
                        @endif
                    </ul>
                </div>
            @elseif ($automatic)
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-200">Every message goes out automatically, at once. The sending history then shows each one as Pending, Sent, Delivered or Failed.</p>
            @else
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                    From your school's WhatsApp: each parent's message opens already typed with their own child's details.
                    Press send, come back, and the next parent is ready. The sending history shows who has been sent.
                </p>
            @endif
        </form>
    </div>
</x-dashboard-layout>
