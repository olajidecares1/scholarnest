{{-- A school's address on the platform, and what decides whether it is served.

     Everything on this card is read through the same code the tenant resolver
     uses (School::subdomainHost(), TenantResolver), so it cannot tell the
     Super Admin a site is live while the address itself answers "unavailable". --}}
@php
    use App\Enums\SubscriptionStatus;
    use App\Services\Tenancy\TenantResolver;

    $baseDomain = config('custom_domain.tenant_base_domain');
    $displaySubscription = $school->subscriptionForDisplay();
    $planName = $displaySubscription?->plan?->name ?? 'No plan yet';

    $subscriptionLabel = match (true) {
        $school->activeSubscription !== null => 'Active',
        $displaySubscription?->status === SubscriptionStatus::Expired => 'Expired',
        $displaySubscription?->status === SubscriptionStatus::Rejected => 'Rejected',
        $displaySubscription !== null => 'Pending activation',
        default => 'None',
    };

    $plannedHost = $baseDomain && $school->subdomain ? "{$school->subdomain}.{$baseDomain}" : null;
    $liveUrl = $school->subdomainUrl();
    $customDomain = $school->primaryCustomDomain;

    $servingState = match (true) {
        ! $baseDomain => ['Subdomains are switched off (TENANT_BASE_DOMAIN is not set)', 'text-amber-700 dark:text-amber-400'],
        ! $school->is_active => ['Not served: school is suspended', 'text-red-700 dark:text-red-400'],
        $school->activeSubscription === null => ['Not served: no active subscription', 'text-red-700 dark:text-red-400'],
        ! TenantResolver::planIncludesSubdomain($school) => ['Not served: the Basic plan has no public website', 'text-gray-600 dark:text-gray-300'],
        ! $school->hasPublicWebsite() => ['Address is live; the website has not been published yet', 'text-amber-700 dark:text-amber-400'],
        default => ['Live', 'text-green-700 dark:text-green-400'],
    };
@endphp

<div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Website Address</h3>

    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Plan</dt><dd class="text-right text-gray-900 dark:text-white">{{ $planName }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Subscription</dt><dd class="text-right text-gray-900 dark:text-white">{{ $subscriptionLabel }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Activation</dt><dd class="text-right text-gray-900 dark:text-white">{{ $school->is_active ? 'Active' : 'Suspended' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Slug</dt><dd class="break-all text-right font-mono text-xs text-gray-900 dark:text-white">{{ $school->slug }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Subdomain</dt><dd class="break-all text-right font-mono text-xs text-gray-900 dark:text-white">{{ $school->subdomain ?? 'N/A' }}</dd></div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Public website</dt>
            <dd class="break-all text-right">
                @if ($liveUrl)
                    <a href="{{ $liveUrl }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $liveUrl }}</a>
                @elseif ($plannedHost)
                    <span class="text-gray-500 dark:text-gray-400">{{ $plannedHost }}</span>
                @else
                    <span class="text-gray-500 dark:text-gray-400">N/A</span>
                @endif
            </dd>
        </div>
        @if ($customDomain)
            <div class="flex justify-between gap-3 sm:col-span-2"><dt class="text-gray-500 dark:text-gray-400">Custom domain</dt><dd class="break-all text-right text-gray-900 dark:text-white">{{ $customDomain->domain }} <span class="text-xs text-gray-500">({{ $customDomain->status?->value }})</span></dd></div>
        @endif
        <div class="flex justify-between gap-3 sm:col-span-2"><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="text-right font-medium {{ $servingState[1] }}">{{ $servingState[0] }}</dd></div>
    </dl>

    <form method="POST" action="{{ route('super-admin.schools.subdomain', $school) }}" class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700"
          onsubmit="return confirm('Change this school\'s website address? The current address will stop working immediately.');">
        @csrf
        <label for="subdomain" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Change subdomain</label>
        <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="flex min-w-0 flex-1 items-center rounded-[8px] border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900">
                <input id="subdomain" name="subdomain" type="text" inputmode="url" autocomplete="off" autocapitalize="none" spellcheck="false"
                       maxlength="63" pattern="[a-z0-9]+" required
                       value="{{ old('subdomain', $school->subdomain) }}"
                       class="min-w-0 flex-1 rounded-l-[8px] border-0 bg-transparent px-3 py-2 font-mono text-sm text-gray-900 focus:ring-0 dark:text-white">
                @if ($baseDomain)
                    <span class="shrink-0 pr-3 font-mono text-xs text-gray-500 dark:text-gray-400">.{{ $baseDomain }}</span>
                @endif
            </div>
            <button type="submit" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Save address</button>
        </div>
        <p class="field-hint mt-1">Lowercase letters and numbers only. The old address stops working as soon as this is saved.</p>
        @error('subdomain')
            <p class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </form>
</div>
