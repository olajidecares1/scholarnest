@php
    use App\Enums\CustomDomainSslStatus;
    use App\Enums\CustomDomainStatus;

    $primary = $domains->firstWhere('is_primary', true);
    $secondaryDomains = $domains->where('is_primary', false);
    $cnameTarget = config('custom_domain.cname_target');
    $aRecordIp = config('custom_domain.a_record_ip');
@endphp

<div class="space-y-6" x-data="{ open: false, editing: null }">
    @if (! $hasCustomDomainAccess)
        <div class="rounded-[5px] border-2 border-dashed border-emerald-200 bg-emerald-50/40 p-10 text-center dark:border-emerald-900/40 dark:bg-emerald-900/10 lg:rounded-[10px]">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                    <path d="M3 12h18M12 3c2.2 2.4 2.2 15.6 0 18M12 3c-2.2 2.4-2.2 15.6 0 18" stroke="currentColor" stroke-width="1.5" />
                </svg>
            </span>
            <h3 class="mt-3 text-lg font-bold text-gray-900 dark:text-white">Connect Your Own Domain</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-600 dark:text-gray-300">Serve your school's website at your own branded domain (e.g. www.schoolname.com) instead of the default ScholarNest link. Custom Domain is available on the Exclusive plan.</p>
            <a
                href="{{ route('subscriptions.choose-plan') }}"
                class="mt-4 inline-flex items-center gap-2 rounded-[8px] bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-md shadow-emerald-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-emerald-700 hover:shadow-lg"
            >
                Upgrade to Exclusive
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </a>
        </div>
    @else
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if (! $primary)
            <div class="rounded-[5px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700 lg:rounded-[10px]">
                <p class="text-sm text-gray-500 dark:text-gray-400">Connect your school's own web address instead of the default ScholarNest link (e.g. www.schoolname.com).</p>
                <button
                    type="button"
                    @click="editing = null; open = true"
                    class="mt-4 inline-flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                    Add Your Domain
                </button>
            </div>
        @else
            @php
                $steps = $primary->wizardSteps();
                $currentStepIndex = collect($steps)->search(fn ($step) => $step['state'] === 'current');
                $currentStepNumber = $currentStepIndex === false ? count($steps) : $currentStepIndex + 1;
                $isLive = $primary->status === CustomDomainStatus::Verified && $primary->ssl_status === CustomDomainSslStatus::Active;
            @endphp

            <div
                class="rounded-[5px] border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
                x-data="customDomainWizard(@js(route('custom-domain.status', $primary)), @js($primary->status->value), @js($primary->ssl_status->value), @js(! $isLive))"
                x-init="init()"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Custom Domain Setup</p>
                        <h3 class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $primary->domain }}</h3>
                    </div>
                    <button
                        type="button"
                        @click="editing = @js(['uuid' => $primary->uuid, 'domain' => $primary->domain]); open = true"
                        class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Replace domain
                    </button>
                </div>

                {{-- Step tracker --}}
                <div class="mt-5 flex flex-wrap items-center gap-x-1 gap-y-3">
                    @foreach ($steps as $i => $step)
                        <div class="flex items-center gap-2">
                            <span @class([
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $step['state'] === 'done',
                                'bg-blue-600 text-white' => $step['state'] === 'current',
                                'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500' => $step['state'] === 'upcoming',
                            ])>
                                @if ($step['state'] === 'done')
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>
                            <span @class([
                                'text-xs font-semibold',
                                'text-gray-400 dark:text-gray-500' => $step['state'] === 'upcoming',
                                'text-gray-900 dark:text-white' => $step['state'] !== 'upcoming',
                            ])>{{ $step['label'] }}</span>
                        </div>
                        @if (! $loop->last)
                            <div class="h-px w-5 shrink-0 sm:w-8 {{ $step['state'] === 'done' ? 'bg-green-300 dark:bg-green-800' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                        @endif
                    @endforeach
                </div>
                <p class="mt-2 text-xs font-semibold text-gray-500 dark:text-gray-400">Step {{ $currentStepNumber }} of {{ count($steps) }}</p>

                @if ($isLive)
                    <div class="mt-5 rounded-[8px] bg-green-50 p-6 text-center dark:bg-green-900/20">
                        <p class="text-base font-bold text-green-700 dark:text-green-400">Your website is live on {{ $primary->domain }}!</p>
                        <a href="https://{{ $primary->domain }}" target="_blank" class="mt-1 inline-block text-sm font-semibold text-green-700 underline dark:text-green-400">https://{{ $primary->domain }} &rarr;</a>
                    </div>
                @else
                    <div class="mt-5 rounded-[8px] bg-blue-50 p-4 text-xs text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                        Saving a domain doesn't connect it right away — it's still managed wherever you registered it (Namecheap, GoDaddy, Cloudflare, Hostinger, Squarespace Domains, Porkbun, and similar). Follow the steps below to point it at ScholarNest.
                    </div>

                    <div class="mt-4 space-y-3 rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">1. Add these DNS records at your domain provider</p>
                        <p class="field-hint">Changing your DNS records tells the internet where visitors should be sent when they type your domain name.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-gray-400 dark:text-gray-500">
                                        <th class="pb-1 pr-4 font-semibold">Type</th>
                                        <th class="pb-1 pr-4 font-semibold">Host</th>
                                        <th class="pb-1 font-semibold">Value</th>
                                    </tr>
                                </thead>
                                <tbody class="font-mono text-gray-700 dark:text-gray-200">
                                    <tr>
                                        <td class="pr-4 pt-1 align-top">TXT</td>
                                        <td class="break-all pr-4 pt-1 align-top">{{ $primary->verificationRecordHost() }}</td>
                                        <td class="break-all pt-1 align-top">{{ $primary->verification_token }}</td>
                                    </tr>
                                    <tr>
                                        <td class="pr-4 pt-1 align-top">CNAME</td>
                                        <td class="break-all pr-4 pt-1 align-top">{{ $primary->domain }}</td>
                                        <td class="break-all pt-1 align-top">{{ $cnameTarget }}</td>
                                    </tr>
                                    @if ($aRecordIp)
                                        <tr>
                                            <td class="pr-4 pt-1 align-top">A (root domains only)</td>
                                            <td class="break-all pr-4 pt-1 align-top">{{ $primary->domain }}</td>
                                            <td class="break-all pt-1 align-top">{{ $aRecordIp }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">Root/apex domains (without "www") may need your provider's ALIAS/ANAME or CNAME-flattening option instead of a plain CNAME — most registrars list this under advanced DNS settings. DNS changes can take a few minutes to a few hours to fully take effect.</p>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">2. Verify your domain</p>
                            @if ($primary->last_check_error)
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $primary->last_check_error }}</p>
                            @else
                                <p class="field-hint mt-1">Once you've added the records above, click verify. We also automatically re-check every few minutes in the background.</p>
                            @endif
                            @if ($primary->last_checked_at)
                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Last checked {{ $primary->last_checked_at->diffForHumans() }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('custom-domain.verify', $primary) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <button type="submit" :disabled="submitting" class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-200 hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70">
                                <svg x-show="submitting" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                                <span x-text="submitting ? 'Verifying…' : 'Verify Domain'"></span>
                            </button>
                        </form>
                    </div>

                    @if ($primary->status === CustomDomainStatus::Verified)
                        <div class="mt-4 flex items-center gap-3 rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                            <svg class="h-5 w-5 shrink-0 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                            <p class="text-sm text-gray-700 dark:text-gray-200">3. Installing your SSL certificate and configuring HTTPS — this usually only takes a moment. This page updates automatically.</p>
                        </div>
                    @endif
                @endif

                @if ($primary->status === CustomDomainStatus::Verified)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-gray-200 p-4 dark:border-gray-700">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">Redirect the default ScholarNest link</p>
                            <p class="field-hint mt-0.5">Visitors to your default ScholarNest web address are redirected to {{ $primary->domain }}.</p>
                        </div>
                        <form method="POST" action="{{ route('custom-domain.toggle-redirect', $primary) }}">
                            @csrf
                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                {{ $primary->redirect_default_domain ? 'On' : 'Off' }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        @if ($primary)
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Subdomains</h4>
                    <button type="button" @click="editing = null; open = true" class="text-xs font-semibold text-blue-600 hover:text-blue-700">+ Add Subdomain</button>
                </div>

                <div class="mt-3 space-y-3">
                    @forelse ($secondaryDomains as $domain)
                        <div class="rounded-[5px] border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $domain->domain }}</p>
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $domain->status === CustomDomainStatus::PendingVerification,
                                            'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $domain->status === CustomDomainStatus::Verified,
                                            'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $domain->status === CustomDomainStatus::Failed,
                                        ])>{{ $domain->status->label() }}</span>
                                    </div>
                                    @if ($domain->status !== CustomDomainStatus::Verified)
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">TXT {{ $domain->verificationRecordHost() }} = {{ $domain->verification_token }} &middot; CNAME {{ $domain->domain }} &rarr; {{ $cnameTarget }}</p>
                                        @if ($domain->last_check_error)
                                            <p class="mt-1 text-[11px] text-red-600 dark:text-red-400">{{ $domain->last_check_error }}</p>
                                        @endif
                                    @else
                                        <a href="https://{{ $domain->domain }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-blue-600 hover:text-blue-700">https://{{ $domain->domain }} &rarr;</a>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('custom-domain.verify', $domain) }}">
                                        @csrf
                                        <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                            {{ $domain->status === CustomDomainStatus::Verified ? 'Recheck' : 'Verify Now' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('custom-domain.set-primary', $domain) }}">
                                        @csrf
                                        <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Set as Primary</button>
                                    </form>
                                    <button
                                        type="button"
                                        @click="editing = @js(['uuid' => $domain->uuid, 'domain' => $domain->domain]); open = true"
                                        class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Replace
                                    </button>
                                    <form method="POST" action="{{ route('custom-domain.destroy', $domain) }}" onsubmit="return confirm('Remove {{ $domain->domain }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-150 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="field-hint">No subdomains yet — e.g. add "portal.{{ $primary->domain }}" if you'd like a dedicated address for a specific area of your site.</p>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Add/Replace Domain modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Replace Domain' : (@js((bool) $primary) ? 'Add Subdomain' : 'Add Your Domain')"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('custom-domain.update', ['domain' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('custom-domain.store') }}'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="domain" label="Domain" icon="M12 3a9 9 0 100 18 9 9 0 000-18z" placeholder="www.schoolname.com or portal.schoolname.com" x-model="editing ? editing.domain : ''" required helper="A domain you already own, without http:// or a trailing slash. You will be given DNS records to add at whoever you bought it from, and your website starts serving here once they have taken effect." />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@once
    <script>
        // Polls the domain's status endpoint while its setup wizard is mid-flow
        // (not yet verified, or verified but SSL isn't Active yet) so the school
        // admin sees progress update automatically instead of needing to
        // manually refresh - reloads the page once the status actually changes,
        // since the wizard's markup is server-rendered from that status.
        function customDomainWizard(statusUrl, initialStatus, initialSslStatus, shouldPoll) {
            return {
                pollHandle: null,
                init() {
                    if (! shouldPoll) {
                        return;
                    }

                    this.pollHandle = setInterval(() => {
                        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                            .then((response) => response.json())
                            .then((data) => {
                                if (data.status !== initialStatus || data.ssl_status !== initialSslStatus) {
                                    window.location.reload();
                                }
                            });
                    }, 6000);
                },
            };
        }
    </script>
@endonce
