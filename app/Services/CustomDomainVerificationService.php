<?php

namespace App\Services;

use App\Enums\CustomDomainStatus;
use App\Jobs\ProvisionCustomDomainSsl;
use App\Models\CustomDomain;

class CustomDomainVerificationService
{
    /**
     * Attempt to verify a domain in two independent steps, so a failure
     * always names the specific thing that's actually wrong rather than a
     * single generic error:
     *
     * 1. Ownership - a TXT record at the domain's "_akademicnest-verify"
     *    subdomain containing its verification token. TXT-based ownership
     *    verification works for both root domains and subdomains, unlike
     *    CNAME which most registrars forbid at the zone apex.
     * 2. Routing - the domain itself actually points at AkademicNest (a CNAME to
     *    the configured target, or an A record to the configured IP for
     *    apex domains). Ownership alone isn't enough to actually serve
     *    traffic - without this check a school could pass verification
     *    while their domain still resolves nowhere.
     */
    public function verify(CustomDomain $domain): bool
    {
        if (! $this->ownershipProven($domain)) {
            $this->fail($domain, "We couldn't find a TXT record at {$domain->verificationRecordHost()} containing your verification token yet. DNS changes can take a few minutes to a few hours to propagate - if you just added the record, please try again shortly.");

            return false;
        }

        $cnameTarget = config('custom_domain.cname_target');
        $aRecordIp = config('custom_domain.a_record_ip');
        $cnameRecords = $this->lookupRecords($domain->domain, DNS_CNAME);
        $aRecords = $aRecordIp !== null ? $this->lookupRecords($domain->domain, DNS_A) : [];

        if (! $this->routingRecordsMatchTarget($cnameRecords, $aRecords, $cnameTarget, $aRecordIp)) {
            $this->fail($domain, empty($cnameRecords) && empty($aRecords)
                ? "Domain ownership was confirmed, but {$domain->domain} has no CNAME record yet. Add a CNAME record for {$domain->domain} pointing to {$cnameTarget}."
                : "Domain ownership was confirmed, but {$domain->domain} isn't pointing to AkademicNest yet. Make sure its CNAME record points to {$cnameTarget}.");

            return false;
        }

        $domain->update([
            'status' => CustomDomainStatus::Verified,
            'verified_at' => now(),
            'last_checked_at' => now(),
            'last_check_error' => null,
        ]);

        ProvisionCustomDomainSsl::dispatch($domain);

        return true;
    }

    /**
     * Whether the school's TXT record proves it owns the domain.
     *
     * The current prefix first, then the ones this platform used before it was
     * renamed - a domain verified under the old name keeps working without the
     * school having to touch its DNS again. Only the current prefix is ever
     * shown to anybody, so nothing new is created under an old name.
     */
    private function ownershipProven(CustomDomain $domain): bool
    {
        $hosts = array_merge([$domain->verificationRecordHost()], $domain->legacyVerificationRecordHosts());

        foreach ($hosts as $host) {
            if ($this->recordsContainToken($this->lookupRecords($host, DNS_TXT), $domain->verification_token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pulled out as a pure method (no network call) so the matching logic
     * itself is unit-testable without real DNS access.
     *
     * @param  list<array{txt?: string}>  $records
     */
    public function recordsContainToken(array $records, string $token): bool
    {
        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pulled out as a pure method for the same reason as recordsContainToken
     * - unit-testable without real DNS access.
     *
     * @param  list<array{target?: string}>  $cnameRecords
     * @param  list<array{ip?: string}>  $aRecords
     */
    public function routingRecordsMatchTarget(array $cnameRecords, array $aRecords, string $cnameTarget, ?string $aRecordIp): bool
    {
        foreach ($cnameRecords as $record) {
            if (rtrim($record['target'] ?? '', '.') === rtrim($cnameTarget, '.')) {
                return true;
            }
        }

        if ($aRecordIp === null) {
            return false;
        }

        foreach ($aRecords as $record) {
            if (($record['ip'] ?? null) === $aRecordIp) {
                return true;
            }
        }

        return false;
    }

    private function fail(CustomDomain $domain, string $reason): void
    {
        $domain->update([
            'status' => CustomDomainStatus::Failed,
            'last_checked_at' => now(),
            'last_check_error' => $reason,
        ]);
    }

    /**
     * The one place this class touches the network. Protected rather than
     * private so a test can answer DNS itself and assert on which hosts were
     * asked - the real lookup is unusable in a test suite.
     *
     * @return list<array<string, mixed>>
     */
    protected function lookupRecords(string $host, int $type): array
    {
        $records = @dns_get_record($host, $type);

        return $records !== false ? $records : [];
    }
}
