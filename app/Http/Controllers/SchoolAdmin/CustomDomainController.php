<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\CustomDomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomDomainController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.custom-domain.index', [
            'school' => $school,
            'domains' => $school->customDomains()->orderByDesc('is_primary')->orderBy('domain')->get(),
            'hasCustomDomainAccess' => true,
        ]);
    }

    /**
     * Lightweight JSON status snapshot the setup wizard polls while a domain
     * is mid-verification or mid-SSL-provisioning, so its progress updates
     * without the school admin needing to manually refresh the page.
     */
    public function status(Request $request, CustomDomain $domain): JsonResponse
    {
        $this->authorizeDomain($domain);

        return response()->json([
            'status' => $domain->status->value,
            'ssl_status' => $domain->ssl_status->value,
            'last_check_error' => $domain->last_check_error,
            'last_checked_at' => $domain->last_checked_at?->diffForHumans(),
            'is_live' => $domain->status === CustomDomainStatus::Verified && $domain->ssl_status === CustomDomainSslStatus::Active,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $this->validated($request, $school->id);

        $domain = DB::transaction(function () use ($school, $validated) {
            $isFirst = $school->customDomains()->count() === 0;

            if ($isFirst) {
                $school->customDomains()->update(['is_primary' => false]);
            }

            return $school->customDomains()->create([
                'domain' => $validated['domain'],
                'is_primary' => $isFirst,
                'verification_token' => Str::random(32),
            ]);
        });

        return back()->with('status', "\"{$domain->domain}\" was added. Add the DNS TXT record shown below, then verify.");
    }

    public function verify(Request $request, CustomDomain $domain, CustomDomainVerificationService $verifier): RedirectResponse
    {
        $this->authorizeDomain($domain);

        $verified = $verifier->verify($domain);

        return back()->with('status', $verified
            ? "\"{$domain->domain}\" was verified successfully."
            : "Verification failed for \"{$domain->domain}\" - check the DNS record and try again.");
    }

    public function update(Request $request, CustomDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($domain);

        $validated = $this->validated($request, $domain->school_id, $domain->id);

        $domain->update([
            'domain' => $validated['domain'],
            'status' => CustomDomainStatus::PendingVerification,
            'verification_token' => Str::random(32),
            'verified_at' => null,
            'ssl_status' => CustomDomainSslStatus::Pending,
            'ssl_issued_at' => null,
        ]);

        return back()->with('status', "Domain updated to \"{$domain->domain}\". Re-verify to activate it.");
    }

    public function setPrimary(Request $request, CustomDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($domain);

        DB::transaction(function () use ($domain) {
            CustomDomain::where('school_id', $domain->school_id)->update(['is_primary' => false]);
            $domain->update(['is_primary' => true]);
        });

        return back()->with('status', "\"{$domain->domain}\" is now the primary domain.");
    }

    public function toggleRedirect(Request $request, CustomDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($domain);

        $domain->update(['redirect_default_domain' => ! $domain->redirect_default_domain]);

        return back()->with('status', $domain->redirect_default_domain
            ? "Visitors to the default link will now be redirected to \"{$domain->domain}\"."
            : 'Redirect to the custom domain was turned off.');
    }

    public function destroy(Request $request, CustomDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($domain);

        $wasPrimary = $domain->is_primary;
        $name = $domain->domain;
        $domain->delete();

        if ($wasPrimary) {
            $next = CustomDomain::where('school_id', $request->user()->school_id)->first();
            $next?->update(['is_primary' => true]);
        }

        return back()->with('status', "\"{$name}\" was removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^(?!https?:\/\/)(?!.*\s)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i',
                Rule::unique('custom_domains', 'domain')->ignore($ignoreId),
            ],
        ], [
            'domain.regex' => 'Enter a valid domain without "http://" (e.g. www.schoolname.com).',
        ]);
    }

    private function authorizeDomain(CustomDomain $domain): void
    {
        $this->authorizeSchoolOwnership($domain);
    }
}
