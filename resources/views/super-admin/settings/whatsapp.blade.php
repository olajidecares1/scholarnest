@php
    $card = 'rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800';
    $verifyBody = '{{1}} is your verification code.';
@endphp

<x-super-admin-layout page-title="WhatsApp" page-subtitle="AkademicNest's WhatsApp number: result details to every school's parents, automatically.">
    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- Where things stand. --}}
        <div class="flex items-start gap-4 rounded-[10px] border p-5 shadow-sm {{ $on ? 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-900/20' : 'border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/20' }}">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $on ? 'bg-[#25D366] text-white' : 'bg-amber-200 text-amber-800 dark:bg-amber-800 dark:text-amber-100' }}">
                <i class="fa-brands fa-whatsapp text-xl" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 text-sm text-gray-800 dark:text-gray-100">
                @if ($on)
                    <p class="font-semibold text-gray-900 dark:text-white">
                        Automatic WhatsApp is on{{ $settings->display_number ? ': '.$settings->display_number : '' }}{{ $settings->verified_name ? ' ('.$settings->verified_name.')' : '' }}
                    </p>
                    <p class="mt-0.5">
                        When any school presses <strong>Send to parents on WhatsApp</strong>, every parent gets their own child's details at once, from this number.
                        @if ($fromServer) Set by the server settings (WHATSAPP_*); saving here replaces them. @endif
                    </p>
                    <p class="mt-1 text-xs">School number verification codes: <strong>{{ $codesOn ? 'on' : 'off (add the verification template below)' }}</strong>.</p>
                @else
                    <p class="font-semibold text-gray-900 dark:text-white">Automatic WhatsApp is off</p>
                    <p class="mt-0.5">Schools send each parent's message by hand from their phone until AkademicNest's WhatsApp details are saved below.</p>
                @endif
            </div>
        </div>

        {{-- What to do in Meta, once. --}}
        <div class="{{ $card }}">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Set up in Meta, once</h2>
            <ol class="mt-3 list-decimal space-y-3 pl-5 text-sm text-gray-700 dark:text-gray-200">
                <li>At <a href="https://business.facebook.com" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline dark:text-primary-300">business.facebook.com</a>, open WhatsApp Manager, add and verify AkademicNest's number, and add a payment method (Meta charges per message). Note the number's <strong>Phone number ID</strong>.</li>
                <li>Create a <strong>system user</strong> access token with the <em>whatsapp_business_messaging</em> permission, one that does not expire.</li>
                <li>
                    Create the result template: category <strong>Utility</strong>, named e.g. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">result_details</code>, with exactly this text:
                    <div class="relative mt-2" x-data="{ copied: false }">
                        <pre class="whitespace-pre-wrap rounded-[8px] border border-gray-200 bg-gray-50 p-3 pr-20 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">{{ $resultTemplateBody }}</pre>
                        <button type="button" class="btn absolute right-2 top-2 rounded-[6px] border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" x-on:click="navigator.clipboard.writeText(@js($resultTemplateBody)); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                        </button>
                    </div>
                </li>
                <li>For schools to verify their own number, create a template of category <strong>Authentication</strong> with a <strong>Copy code</strong> button, named e.g. <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">verify_code</code>. Meta writes its text.</li>
                <li>Wait for Meta to approve the templates, then enter everything below.</li>
            </ol>
        </div>

        {{-- The details. --}}
        <form method="POST" action="{{ route('super-admin.settings.whatsapp.update') }}" class="{{ $card }} space-y-4">
            @csrf
            @method('PUT')
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">AkademicNest's WhatsApp details</h2>

            <div>
                <label for="phone_number_id" class="field-label mb-1">Phone number ID</label>
                <input id="phone_number_id" name="phone_number_id" inputmode="numeric" autocomplete="off" value="{{ old('phone_number_id', $settings->phone_number_id) }}" placeholder="e.g. 106540352242922" class="w-full font-mono" required>
            </div>
            <div>
                <label for="access_token" class="field-label mb-1">Access token</label>
                <input id="access_token" name="access_token" type="password" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="{{ $tokenMask ? 'Saved ('.$tokenMask.'). Leave blank to keep it.' : 'Paste the permanent token from Meta' }}" class="w-full font-mono" {{ $tokenMask ? '' : 'required' }}>
                <small class="field-hint mt-1">Kept encrypted, and never shown again in full.</small>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="result_template" class="field-label mb-1">Result template name</label>
                    <input id="result_template" name="result_template" autocomplete="off" autocapitalize="none" spellcheck="false" value="{{ old('result_template', $settings->result_template ?? 'result_details') }}" class="w-full font-mono" required>
                </div>
                <div>
                    <label for="template_language" class="field-label mb-1">Language</label>
                    <input id="template_language" name="template_language" autocomplete="off" autocapitalize="none" spellcheck="false" value="{{ old('template_language', $settings->template_language ?? 'en') }}" class="w-full font-mono" required>
                </div>
            </div>
            <div>
                <label for="verify_template" class="field-label mb-1">Verification template name <span class="font-normal">(optional)</span></label>
                <input id="verify_template" name="verify_template" autocomplete="off" autocapitalize="none" spellcheck="false" value="{{ old('verify_template', $settings->verify_template) }}" placeholder="e.g. verify_code" class="w-full font-mono">
                <small class="field-hint mt-1">For schools verifying their own WhatsApp number with a 6-digit code.</small>
            </div>
            <div>
                <label for="app_secret" class="field-label mb-1">App secret <span class="font-normal">(optional, for delivery receipts)</span></label>
                <input id="app_secret" name="app_secret" type="password" autocomplete="off" placeholder="{{ $secretMask ? 'Saved ('.$secretMask.'). Leave blank to keep it.' : 'From the Meta app: App settings > Basic' }}" class="w-full font-mono">
            </div>

            <button type="submit" class="btn inline-flex items-center gap-2 rounded-[8px] bg-[#25D366] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1ebe5b]">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Check with Meta &amp; save
            </button>
        </form>

        @if ($settings->webhook_verify_token)
            {{-- Delivery receipts: Sent becomes Delivered, and failures are reported. --}}
            <div class="{{ $card }}">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Delivery receipts (Delivered / Failed)</h2>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">In the Meta app, under WhatsApp &rarr; Configuration, set the webhook to these and subscribe to <strong>messages</strong>:</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="font-semibold text-gray-900 dark:text-white">Callback URL</dt><dd class="break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $webhookUrl }}</dd></div>
                    <div><dt class="font-semibold text-gray-900 dark:text-white">Verify token</dt><dd class="break-all font-mono text-xs text-gray-800 dark:text-gray-200">{{ $settings->webhook_verify_token }}</dd></div>
                </dl>
            </div>
        @endif

        @if ($on)
            <form method="POST" action="{{ route('super-admin.settings.whatsapp.test') }}" class="{{ $card }} space-y-3">
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Send a test message</h2>
                <p class="text-sm text-gray-700 dark:text-gray-200">The result template with sample values, to a phone you choose.</p>
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
        @endif

        @if ($settings->phone_number_id)
            <form method="POST" action="{{ route('super-admin.settings.whatsapp.destroy') }}" onsubmit="return confirm('Remove AkademicNest\'s WhatsApp details? Automatic WhatsApp sending stops for every school.')" class="text-right">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400">Remove WhatsApp details</button>
            </form>
        @endif
    </div>
</x-super-admin-layout>
