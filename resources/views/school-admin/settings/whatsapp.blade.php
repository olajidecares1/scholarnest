<x-dashboard-layout page-title="WhatsApp" page-subtitle="Verify your school's WhatsApp number. Result details then go to parents on WhatsApp automatically.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @php
            $otherErrors = collect($errors->getMessages())->except(['whatsapp_number', 'code'])->flatten();
            $advancedOpen = $otherErrors->isNotEmpty() || old('phone_number_id') || old('test_phone');
        @endphp

        {{-- The school's WhatsApp number, ordinary or Business, verified by a code. --}}
        <div class="rounded-[10px] border p-5 shadow-sm {{ $verified ? 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-900/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $verified ? 'bg-[#25D366] text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-200' }}">
                    <i class="fa-brands fa-whatsapp text-xl" aria-hidden="true"></i>
                </span>
                <div class="min-w-0">
                    @if ($verified)
                        <p class="font-semibold text-gray-900 dark:text-white">
                            Verified: {{ \App\Support\WhatsAppNumber::display($verified) }}
                            <i class="fa-solid fa-circle-check ml-1 text-[#1ebe5b]" aria-hidden="true"></i>
                        </p>
                        <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">
                            Confirmed on WhatsApp {{ $school->whatsapp_verified_at && $school->whatsapp_verified_at->gt(now()->subMinute()) ? 'just now' : $school->whatsapp_verified_at?->diffForHumans() }}.
                            When you press <strong>Send to parents</strong> on Results, each parent gets their child's admission number, result token and link on WhatsApp automatically,
                            with this number on the message so they can chat with the school.
                        </p>
                    @elseif (! $canVerify)
                        <p class="font-semibold text-gray-900 dark:text-white">Sending result details on WhatsApp</p>
                    @else
                        <p class="font-semibold text-gray-900 dark:text-white">Your school's WhatsApp number</p>
                        <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">
                            Ordinary WhatsApp or WhatsApp Business, both work. We send a 6-digit code to it on WhatsApp; type it here and the number is verified.
                            Nothing to link or log in to.
                        </p>
                    @endif
                </div>
            </div>

            @if (! $canVerify)
                {{-- No AkademicNest number to send codes from: WhatsApp goes
                     from the school's own phone, free. --}}
                <div class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    <p>
                        Result details go to parents from <strong>your own phone</strong>, free, on ordinary WhatsApp or WhatsApp Business.
                        On <strong>Results</strong>, tick the pupils (or Select all) and choose <strong>Send to parents</strong>, then tap <strong>Send to all on WhatsApp</strong>:
                        WhatsApp opens at each parent's chat with the admission number, token and link already typed. Press send, come back, and the next parent is ready.
                    </p>
                    <p class="text-xs">Send from the phone that has your school's WhatsApp, so parents see the school's number.</p>
                </div>
            @elseif ($pending)
                {{-- A code is on its way: type it back. --}}
                <form method="POST" action="{{ route('settings.whatsapp.number.verify') }}" class="mt-4 space-y-2">
                    @csrf
                    <label for="code" class="field-label">
                        Enter the 6-digit code sent to {{ \App\Support\WhatsAppNumber::display($pending['number']) }} on WhatsApp
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" class="w-40 text-center font-mono text-lg tracking-[0.3em]" required autofocus>
                        <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1ebe5b]">
                            <i class="fa-solid fa-check" aria-hidden="true"></i> Verify
                        </button>
                    </div>
                    @error('code')<p class="field-error">{{ $message }}</p>@enderror
                    @if ($pending['failed'])
                        <p class="field-error">
                            WhatsApp could not deliver the code ({{ $pending['failed'] }}). Check that this number is registered on WhatsApp, or use a different number.
                        </p>
                    @else
                        <small class="field-hint">The code lasts 10 minutes. Not arrived? Check the number is on WhatsApp and that the phone is online.</small>
                    @endif
                </form>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <form method="POST" action="{{ route('settings.whatsapp.number.code') }}">
                        @csrf
                        <input type="hidden" name="whatsapp_number" value="{{ $pending['number'] }}">
                        <button type="submit" class="font-semibold text-primary-700 hover:underline disabled:text-gray-600 disabled:no-underline dark:text-primary-300"
                                x-data="{ wait: {{ (int) $resendIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }" x-bind:disabled="wait > 0">
                            Send a new code<span x-show="wait > 0" x-text="' (' + wait + 's)'"></span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('settings.whatsapp.number.cancel') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold text-gray-700 hover:underline dark:text-gray-200">Use a different number</button>
                    </form>
                </div>
                @error('whatsapp_number')<p class="field-error mt-2">{{ $message }}</p>@enderror
            @else
                {{-- Enter (or change) the number, and get a code. --}}
                <form method="POST" action="{{ route('settings.whatsapp.number.code') }}" class="mt-4 space-y-2">
                    @csrf
                    <label for="whatsapp_number" class="field-label">{{ $verified ? 'Change the number' : 'WhatsApp number' }}</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="tel" autocomplete="tel"
                               value="{{ old('whatsapp_number', $verified ? '' : $school->whatsapp_number) }}" placeholder="e.g. 08031234567"
                               class="min-w-0 flex-1 sm:max-w-xs" required>
                        <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1ebe5b] disabled:opacity-60">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Send code
                        </button>
                    </div>
                    @error('whatsapp_number')<p class="field-error">{{ $message }}</p>@enderror
                    <small class="field-hint">Numbers outside Nigeria: include the country code, e.g. +44 7700 900123.</small>
                </form>
            @endif
        </div>

        @if ($otherErrors->isNotEmpty())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($otherErrors as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- Optional: send from the school's own WhatsApp Business Platform number. --}}
        <details class="group rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" @if ($advancedOpen) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-3 p-5">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-gray-900 dark:text-white">
                        Optional: send from your own number
                        @if ($account)<span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900/40 dark:text-green-300">Connected</span>@endif
                    </span>
                    <span class="mt-0.5 block text-sm text-gray-700 dark:text-gray-200">
                        @if ($account)
                            Result details go out from {{ $account->display_number ?? 'your WhatsApp Business number' }}{{ $account->verified_name ? ' ('.$account->verified_name.')' : '' }}.
                        @else
                            Paid option: schools on Meta's WhatsApp Business Platform can send automatically from their own number. Meta charges per message.
                        @endif
                    </span>
                </span>
                <i class="fa-solid fa-chevron-down text-gray-600 transition group-open:rotate-180 dark:text-gray-300" aria-hidden="true"></i>
            </summary>

            <div class="space-y-6 border-t border-gray-200 p-5 dark:border-gray-700">
        {{-- What to do in Meta, once. --}}
        <div class="rounded-[10px] bg-gray-50 p-4 dark:bg-gray-900/40">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Before you connect</h2>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                Your number must be registered on the <strong>WhatsApp Business Platform</strong> (Meta's WhatsApp Cloud API), in your school's Meta Business account at
                <a href="https://business.facebook.com" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline dark:text-primary-300">business.facebook.com</a>.
                A number registered there sends automatically; nobody needs to be logged in to WhatsApp.
            </p>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-200">
                <li>In WhatsApp Manager, add and verify your school's number. Note its <strong>Phone number ID</strong>.</li>
                <li>Create a <strong>system user access token</strong> with the <em>whatsapp_business_messaging</em> permission (one that does not expire).</li>
                <li>
                    Create a message template, category <strong>Utility</strong>, named for example <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">result_details</code>, with exactly this text, then wait for Meta to approve it:
                    <div class="relative mt-2" x-data="{ copied: false }">
                        <pre class="whitespace-pre-wrap rounded-[8px] border border-gray-200 bg-gray-50 p-3 pr-20 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">{{ $templateBody }}</pre>
                        <button type="button" class="btn absolute right-2 top-2 rounded-[6px] border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" x-on:click="navigator.clipboard.writeText(@js($templateBody)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                        </button>
                    </div>
                </li>
                <li>Enter the details below. They are checked with Meta before they are saved.</li>
            </ol>
        </div>

        {{-- The details. --}}
        <form method="POST" action="{{ route('settings.whatsapp.update') }}" class="space-y-4 rounded-[10px] bg-gray-50 p-4 dark:bg-gray-900/40">
            @csrf
            @method('PUT')
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $account ? 'Your WhatsApp Business details' : 'Connect your WhatsApp Business number' }}</h2>

            <div>
                <label for="phone_number_id" class="field-label mb-1">Phone number ID</label>
                <input id="phone_number_id" name="phone_number_id" inputmode="numeric" autocomplete="off" value="{{ old('phone_number_id', $account?->phone_number_id) }}" placeholder="e.g. 106540352242922" class="w-full font-mono" required>
                <small class="field-hint mt-1">The long number Meta shows under your WhatsApp number. Not the phone number itself.</small>
            </div>

            <div>
                <label for="access_token" class="field-label mb-1">Access token</label>
                <input id="access_token" name="access_token" type="password" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="{{ $account ? 'Saved ('.$account->maskedToken().'). Leave blank to keep it.' : 'Paste the token from Meta' }}" class="w-full font-mono" {{ $account ? '' : 'required' }}>
                <small class="field-hint mt-1">Kept encrypted, and never shown again in full.</small>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="result_template" class="field-label mb-1">Template name</label>
                    <input id="result_template" name="result_template" autocomplete="off" autocapitalize="none" spellcheck="false" value="{{ old('result_template', $account?->result_template ?? 'result_details') }}" class="w-full font-mono" required>
                </div>
                <div>
                    <label for="template_language" class="field-label mb-1">Template language</label>
                    <input id="template_language" name="template_language" autocomplete="off" autocapitalize="none" spellcheck="false" value="{{ old('template_language', $account?->template_language ?? 'en') }}" class="w-full font-mono" required>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1ebe5b]">
                    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> {{ $account ? 'Check & save' : 'Check & connect' }}
                </button>
            </div>
        </form>

        @if ($account)
            {{-- Prove it end to end. --}}
            <form method="POST" action="{{ route('settings.whatsapp.test') }}" class="space-y-3 rounded-[10px] bg-gray-50 p-4 dark:bg-gray-900/40">
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Send a test message</h2>
                <p class="text-sm text-gray-700 dark:text-gray-200">Sends your result template, with sample values, to a phone you choose: yours.</p>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1">
                        <label for="test_phone" class="field-label mb-1">Phone number</label>
                        <input id="test_phone" name="test_phone" type="tel" inputmode="tel" value="{{ old('test_phone') }}" placeholder="08031234567" class="w-full" required>
                    </div>
                    <button type="submit" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i> Send test
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('settings.whatsapp.destroy') }}" onsubmit="return confirm('Disconnect your WhatsApp number? Result details will then go by email, with Send on WhatsApp buttons for WhatsApp.')" class="text-right">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400">Disconnect WhatsApp</button>
            </form>
        @endif
            </div>
        </details>
    </div>
</x-dashboard-layout>
