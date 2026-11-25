{{-- One WhatsApp broadcast for a class's result. See ResultBroadcastController. --}}
@php
    $step = 'flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-sm font-bold text-white';
    $card = 'rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    $n = 1;
@endphp

<x-dashboard-layout page-title="WhatsApp broadcast" page-subtitle="{{ $examination->class_name }} · {{ $examination->term->label() }} {{ $examination->session }}">
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('results.index', ['class' => $examination->class_name, 'session' => $examination->session, 'term' => $examination->term->value]) }}" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                <i class="fa-solid fa-chevron-left text-[12px]" aria-hidden="true"></i> Results
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        <div class="rounded-[10px] border border-green-200 bg-green-50 p-4 text-sm text-gray-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-gray-100">
            <i class="fa-brands fa-whatsapp mr-1 text-[#1ebe5b]" aria-hidden="true"></i>
            One message, sent once from your phone, reaches every {{ $examination->class_name }} parent at the same time. Free.
            The message is the same for everyone, so it carries no tokens: each parent finds their own child's token
            {{ $portals ? 'in their parent portal' : 'in the email sent to them' }}.
        </div>

        @unless ($resultLinkLive)
            <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                Your result-checking link is switched off, so parents cannot check results yet. Switch it on under Exam Tokens first.
            </div>
        @endunless

        {{-- 1. Tokens where only each child's parents can see them. --}}
        @if ($portals)
            <div class="{{ $card }}">
                <div class="flex items-start gap-3">
                    <span class="{{ $step }}">{{ $n++ }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold text-gray-900 dark:text-white">Put each pupil's token in their parents' portal</h2>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                            {{ $pupils }} {{ Str::plural('pupil', $pupils) }}; {{ $withAccount }} {{ $withAccount === 1 ? 'has' : 'have' }} a parent with a portal account.
                            A pupil with no token is given one. Parents are never sent the same token twice, so tapping again is safe.
                        </p>
                        <form method="POST" action="{{ route('result-pins.details.broadcast.portals', $examination->uuid) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                                <i class="fa-solid fa-key" aria-hidden="true"></i> Post tokens to portals
                            </button>
                        </form>
                        @if ($withoutAccount->isNotEmpty())
                            <details class="mt-3 text-sm">
                                <summary class="cursor-pointer font-semibold text-amber-800 dark:text-amber-300">
                                    {{ $withoutAccount->count() }} {{ Str::plural('pupil', $withoutAccount->count()) }} with no parent portal account
                                </summary>
                                <p class="mt-1 text-gray-700 dark:text-gray-200">
                                    Their parents cannot see a token in a portal. Add a parent account on the pupil's record, or send them their own details with <strong>Send to parents</strong> on Results:
                                    {{ $withoutAccount->join(', ') }}.
                                </p>
                            </details>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- 2. The parents' numbers, all saved at once. --}}
        <div class="{{ $card }}">
            <div class="flex items-start gap-3">
                <span class="{{ $step }}">{{ $n++ }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-gray-900 dark:text-white">Save the parents' numbers on your phone</h2>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                        {{ $parentNumbers }} parent WhatsApp {{ Str::plural('number', $parentNumbers) }} from the pupils' records, in one contacts file.
                        Open it on the phone that has your school's WhatsApp and choose to add them all.
                    </p>
                    <a href="{{ route('result-pins.details.broadcast.contacts', $examination->uuid) }}" class="btn mt-3 inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700 {{ $parentNumbers === 0 ? 'pointer-events-none opacity-50' : '' }}">
                        <i class="fa-solid fa-address-book" aria-hidden="true"></i> Download {{ $parentNumbers }} {{ Str::plural('contact', $parentNumbers) }}
                    </a>
                    @if ($pupilsWithoutNumber > 0)
                        <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">
                            {{ $pupilsWithoutNumber }} {{ Str::plural('pupil', $pupilsWithoutNumber) }} {{ $pupilsWithoutNumber === 1 ? 'has' : 'have' }} no parent WhatsApp number on record, so they are not in the file.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3. The broadcast list, in WhatsApp. --}}
        <div class="{{ $card }}">
            <div class="flex items-start gap-3">
                <span class="{{ $step }}">{{ $n++ }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-gray-900 dark:text-white">Make a broadcast list in WhatsApp (once per class)</h2>
                    <ul class="mt-2 space-y-1.5 text-sm text-gray-700 dark:text-gray-200">
                        <li><strong>Android:</strong> Chats &rarr; &#8942; (top right) &rarr; New broadcast.</li>
                        <li><strong>iPhone:</strong> Chats &rarr; Broadcast Lists (or &hellip; &rarr; New broadcast) &rarr; New List.</li>
                        <li>Tick the parents, then Create. Name it, e.g. &ldquo;{{ $examination->class_name }} parents&rdquo;, and reuse it every term.</li>
                    </ul>
                    <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">
                        WhatsApp delivers a broadcast only to parents who have saved your school's number too.
                        Ask them to save it (or add it to the message) so nobody misses it.
                    </p>
                </div>
            </div>
        </div>

        {{-- 4. The message: copy, paste into the broadcast list, send once. --}}
        <div class="{{ $card }}" x-data="{ text: @js($message), copied: false }">
            <div class="flex items-start gap-3">
                <span class="{{ $step }}">{{ $n++ }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-gray-900 dark:text-white">Send this message to the broadcast list</h2>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Change it if you like. Copy it, open your broadcast list in WhatsApp, paste and send.</p>
                    <label for="broadcast-message" class="sr-only">Message</label>
                    <textarea id="broadcast-message" x-model="text" rows="11" class="mt-3 w-full text-sm leading-relaxed"></textarea>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn inline-flex items-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2 text-sm font-bold text-white hover:bg-[#1ebe5b]"
                                x-on:click="navigator.clipboard.writeText(text).then(() => { copied = true; setTimeout(() => copied = false, 2500) })">
                            <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                            <span x-text="copied ? 'Copied' : 'Copy message'"></span>
                        </button>
                        <a :href="'https://wa.me/?text=' + encodeURIComponent(text)" target="_blank" rel="noopener" class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Open in WhatsApp
                        </a>
                    </div>
                    <small class="field-hint mt-2">&ldquo;Open in WhatsApp&rdquo; opens WhatsApp with the message typed: choose your broadcast list there, if it is offered, or paste it into the list.</small>
                </div>
            </div>
        </div>
    </div>
</x-dashboard-layout>
