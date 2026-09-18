<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Tenancy\TenantResolution;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Console\Command;

/**
 * Every school, its subdomain, and whether that address will actually serve.
 *
 * `production:urls` answers "is the platform configured for subdomains at
 * all". This answers the question that follows it, and the one that is
 * actually asked when a school says their address does not work: WHICH
 * schools resolve, and for the ones that do not, why not. The reasons are
 * genuinely different and want different fixes:
 *
 *   no subdomain stored   a row the backfill never reached. --repair fills it
 *   plan                  Basic, which has no website by design
 *   subscription          expired, rejected, or still awaiting approval
 *   suspended             is_active is false
 *
 * The verdict comes from TenantResolver itself, not from a second copy of its
 * rules, so this reports what a browser would get and cannot drift from it.
 */
class CheckSchoolSubdomains extends Command
{
    protected $signature = 'schools:subdomains {--repair : Give a subdomain to any school missing one}';

    protected $description = 'Report every school\'s subdomain and whether it resolves, and optionally repair missing ones';

    public function handle(TenantResolver $resolver): int
    {
        $base = $resolver->baseDomain();

        if ($base === null) {
            $this->components->error(
                'No base domain configured, so no school subdomain resolves. Set TENANT_BASE_DOMAIN, or APP_URL in production.'
            );

            return self::FAILURE;
        }

        $this->components->info("School subdomains under {$base}");

        if ($this->option('repair')) {
            $this->repair();
        }

        $rows = [];
        $serving = 0;
        $broken = 0;

        School::with(['activeSubscription.plan', 'website'])
            ->orderBy('name')
            ->chunk(100, function ($schools) use ($resolver, $base, &$rows, &$serving, &$broken) {
                foreach ($schools as $school) {
                    $label = $school->subdomain ?: $school->slug;
                    $resolution = $resolver->resolve("{$label}.{$base}");
                    $verdict = $this->verdict($school, $resolution);

                    $resolution->isTenantHost() && $resolution->status !== TenantResolution::UNAVAILABLE
                        ? $serving++
                        : $broken++;

                    $rows[] = [
                        $school->name,
                        $school->activeSubscription?->plan?->key?->label() ?? '—',
                        $school->subdomain ?: '<fg=red>none</>',
                        $school->subdomainHost() ? "https://{$school->subdomainHost()}/" : '—',
                        $verdict,
                    ];
                }
            });

        if ($rows === []) {
            $this->components->warn('No schools on this deployment.');

            return self::SUCCESS;
        }

        $this->table(['School', 'Plan', 'Subdomain', 'Address', 'Verdict'], $rows);

        $this->newLine();
        $this->line("  <fg=green>{$serving}</> serving, <fg=yellow>{$broken}</> not.");

        if ($broken > 0) {
            $this->line('  <fg=gray>A "no subdomain stored" row is fixed by re-running this with --repair.</>');
            $this->line('  <fg=gray>Everything else is the plan or the subscription, and is fixed in the Super Admin.</>');
        }

        return self::SUCCESS;
    }

    /**
     * What a browser asking for this address would get.
     */
    private function verdict(School $school, TenantResolution $resolution): string
    {
        if (! $school->subdomain) {
            return '<fg=red>no subdomain stored</>';
        }

        return match (true) {
            $resolution->status === TenantResolution::RESOLVED => '<fg=green>serves</>',
            $resolution->status === TenantResolution::ALIAS => '<fg=green>serves (redirects to canonical)</>',
            $resolution->reason === 'plan' => '<fg=gray>no website on this plan</>',
            $resolution->reason === 'subscription' => '<fg=yellow>no active subscription</>',
            $resolution->reason === 'suspended' => '<fg=yellow>school suspended</>',
            default => '<fg=red>404 — nothing answers here</>',
        };
    }

    /**
     * Fill in any school with no subdomain.
     *
     * Done one at a time through availableSubdomain(), passing the labels
     * already handed out in this run, so two schools repaired in the same
     * pass cannot be given the same address.
     */
    private function repair(): void
    {
        $taken = [];
        $fixed = 0;

        School::whereNull('subdomain')->orWhere('subdomain', '')->orderBy('id')
            ->chunkById(100, function ($schools) use (&$taken, &$fixed) {
                foreach ($schools as $school) {
                    $subdomain = School::availableSubdomain($school->name ?: $school->slug, $taken);
                    $taken[] = $subdomain;

                    $school->forceFill(['subdomain' => $subdomain])->saveQuietly();
                    $fixed++;

                    $this->line("    <fg=green>+</> {$school->name}  <fg=gray>-></>  {$subdomain}");
                }
            });

        $this->newLine();
        $this->components->info($fixed === 0 ? 'Every school already had a subdomain.' : "{$fixed} school(s) given a subdomain.");
        $this->newLine();
    }
}
