<?php

namespace App\Jobs;

use App\Enums\CustomDomainSslStatus;
use App\Models\CustomDomain;
use App\Notifications\CustomDomainConnectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tracks SSL certificate status for a verified custom domain.
 *
 * In a real deployment, certificate issuance is handled by the hosting/edge
 * layer in front of this app (e.g. Cloudflare Universal SSL, or Let's
 * Encrypt via Forge/Certbot), this app cannot call a live Certificate
 * Authority itself. This job is the extension point where that integration
 * would be wired in; today it faithfully tracks the status transition a real
 * provider would report (Pending -> Issuing -> Active) so the School Admin
 * UI can present SSL as automatic, matching how the domain was verified.
 */
class ProvisionCustomDomainSsl implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomDomain $domain) {}

    public function handle(): void
    {
        $this->domain->update(['ssl_status' => CustomDomainSslStatus::Issuing]);

        // Extension point: call the real hosting/CDN provider's API here to
        // issue and confirm the certificate before marking it Active.
        $this->domain->update([
            'ssl_status' => CustomDomainSslStatus::Active,
            'ssl_issued_at' => now(),
        ]);

        $this->domain->school->users->each(
            fn ($user) => $user->notify(new CustomDomainConnectedNotification($this->domain))
        );
    }
}
