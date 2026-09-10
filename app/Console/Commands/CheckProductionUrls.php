<?php

namespace App\Console\Commands;

use App\Enums\PlanKey;
use App\Models\School;
use Illuminate\Console\Command;

/**
 * Every address this platform will answer at, and whether it is configured.
 *
 * Four separate address schemes, one per tier plus the platform's own, and
 * each fails differently when its configuration is missing:
 *
 *   Platform     APP_URL wrong -> every generated link goes nowhere.
 *   Basic        token missing -> the portal 404s, and a Basic school has no
 *                                 other way in at all.
 *   Standard     base domain missing -> subdomains silently fall back to
 *                                 /p/{key} paths. Works; is not what was sold.
 *   Exclusive    DNS target missing -> the setup wizard cannot tell a school
 *                                 what record to create.
 *
 * Only the first is fatal enough to refuse to serve (see
 * App\Support\ProductionConfiguration). The rest degrade quietly, which is
 * precisely why they need something that asks out loud.
 */
class CheckProductionUrls extends Command
{
    protected $signature = 'production:urls';

    protected $description = 'Report every URL this platform serves, per plan, and what is missing';

    private int $problems = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Addresses this deployment will serve');

        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);

        $this->platform($appUrl, $host);
        $this->basic($appUrl);
        $this->standard($appUrl, $host);
        $this->exclusive($host);
        $this->examples();

        $this->newLine();

        if ($this->problems > 0) {
            $this->components->error("{$this->problems} setting(s) will break addresses in production.");

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->components->warn("{$this->warnings} optional setting(s) not configured - see above for what each one costs.");

            return self::SUCCESS;
        }

        $this->components->info('Every address is configured.');

        return self::SUCCESS;
    }

    private function platform(string $appUrl, ?string $host): void
    {
        $this->section('Platform', 'APP_URL — every generated link, every email link, and the host stray traffic is redirected to');

        if (! $host) {
            $this->bad('APP_URL', 'missing or unparseable', 'APP_URL=https://your-domain');

            return;
        }

        $isHttps = parse_url($appUrl, PHP_URL_SCHEME) === 'https';
        $isLocal = $host === 'localhost'
            || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || $host === 'lvh.me'
            || str_ends_with($host, '.lvh.me');

        if ($isLocal) {
            $this->bad('APP_URL', $appUrl.' is a local address', 'Set it to the domain the platform is served at');
        } elseif (! $isHttps) {
            $this->bad('APP_URL', $appUrl.' is not https', 'Production forces https on every link it generates');
        } else {
            $this->good('APP_URL', $appUrl);
        }

        $this->line('    Registration      '.$appUrl.'/'.$this->hint('obfuscated path'));
        $this->line('    Legal documents   '.$appUrl.'/legal');
        $this->line('    Unified sign-in   '.$appUrl.'/portal/sign-in');
    }

    private function basic(string $appUrl): void
    {
        $this->section('Basic plan', 'One shared entry point for every Basic school. No public website, no subdomain');

        $token = (string) config('basic_portal.token');

        if ($token === '') {
            $this->bad(
                'BASIC_PORTAL_TOKEN',
                'not set — the Basic portal returns 404 and Basic schools have no way in',
                'Run `php artisan basic-portal:token` and put the result in .env',
            );
        } elseif (strlen($token) !== 32 || ! ctype_alnum($token)) {
            $this->bad(
                'BASIC_PORTAL_TOKEN',
                'must be exactly 32 alphanumeric characters — the route pattern will not match it',
                'Run `php artisan basic-portal:token`',
            );
        } else {
            $this->good('BASIC_PORTAL_TOKEN', 'set (32 characters)');
            $this->newLine();

            // The whole Basic address set, in the order somebody meets it.
            // Everything a Basic school has lives on the platform host: there
            // is no subdomain and no website, and the portal landing is the
            // school's front door.
            //
            // STAFF IS THE ONLY PORTAL HERE, and that is the plan, not an
            // omission. Teachers take attendance and enter marks on every
            // plan; pupil and parent accounts are what Standard buys. This
            // list used to include pupil and parent sign-in, which described
            // a Basic school as having two portals it cannot use - the hub
            // does not link them, no login details can be issued for them,
            // and EnsureSchoolHasPortalAccess turns away anyone who reaches
            // one.
            $this->line('    Front door        '.$appUrl.'/'.$token.'  <fg=gray>(name your school)</>');
            $this->line('    School landing    '.$appUrl.'/'.$this->hint('portal_key'));
            $this->line('    Website           <fg=gray>none — Standard and above</>');
            $this->line('    Portal hub        '.$appUrl.'/p/'.$this->hint('portal_key').'/portal  <fg=gray>(staff only)</>');
            $this->line('    Staff sign-in     '.$appUrl.'/p/'.$this->hint('portal_key').'/staff-portal/'.$this->hint('token').'/login');
            $this->line('    Pupil portal      <fg=gray>none — Standard and above</>');
            $this->line('    Parent portal     <fg=gray>none — Standard and above</>');
            $this->line('    Results           '.$appUrl.'/'.$this->hint('result_link_slug').'/result  <fg=gray>(exam token, no account needed)</>');
        }
    }

    private function standard(string $appUrl, ?string $host): void
    {
        $this->section('Standard plan', 'A subdomain per school, on top of everything Basic has');

        $base = (string) config('custom_domain.tenant_base_domain');

        if ($base === '') {
            $this->optional(
                'TENANT_BASE_DOMAIN',
                'not set — Standard schools fall back to /p/{key} paths instead of their own subdomain',
                'Set it to the domain that has a wildcard DNS record, e.g. TENANT_BASE_DOMAIN='.($host ?: 'your-domain'),
            );

            return;
        }

        $this->good('TENANT_BASE_DOMAIN', $base);
        $this->newLine();

        // A Standard school gets its own host, and the website AND all four
        // portals move onto it. What does NOT move is result checking: it
        // stays on the platform host for every plan, so one address shape
        // covers results whatever a school is paying.
        $site = 'https://'.$this->hint('subdomain').'.'.$base;

        $this->line('    Website           '.$site.'/');
        $this->line('    Portal hub        '.$site.'/portal');
        $this->line('    Staff sign-in     '.$site.'/staff-portal/'.$this->hint('token').'/login');

        // The two portals Standard actually buys. A pupil and a parent read
        // their results INSIDE these, unlocking each with the exam token -
        // the token flow below is the Basic route to the same results, for
        // families with no account at all.
        $this->line('    Pupil sign-in     '.$site.'/portal/'.$this->hint('token').'/login  <fg=gray>(results in-portal)</>');
        $this->line('    Parent sign-in    '.$site.'/parent-portal/'.$this->hint('token').'/login  <fg=gray>(results in-portal)</>');
        $this->line('    Results           '.($appUrl ?: 'https://'.$base).'/'.$this->hint('result_link_slug').'/result  <fg=gray>(platform host, every plan)</>');
        $this->newLine();
        $this->line('    <fg=gray>The Basic paths above keep working too - they are not withdrawn.</>');
        $this->newLine();
        $this->line('    <fg=yellow>Requires, outside this application:</>');
        $this->line('      DNS   a wildcard A/AAAA record  *.'.$base);
        $this->line('      TLS   a wildcard certificate    *.'.$base);
        $this->line('      Without both, every Standard school gets a certificate warning.');
    }

    private function exclusive(?string $host): void
    {
        $this->section('Exclusive plan', "A school's own domain. On its own host no key is needed — the domain identifies the school");

        if (PlanKey::Exclusive->isAvailableToSubscribe() === false) {
            $this->line('    <fg=gray>Note: Exclusive cannot currently be subscribed to. These settings only</>');
            $this->line('    <fg=gray>matter for schools already on it, or once the plan is opened.</>');
            $this->newLine();
        }

        $cname = (string) config('custom_domain.cname_target');
        $aRecord = (string) config('custom_domain.a_record_ip');
        $txt = (string) config('custom_domain.txt_verification_prefix');

        if ($aRecord === '') {
            $this->optional(
                'CUSTOM_DOMAIN_A_RECORD_IP',
                'not set — the setup wizard cannot tell a school which A record to create',
                'Set it to this server\'s public IP address',
            );
        } else {
            $this->good('CUSTOM_DOMAIN_A_RECORD_IP', $aRecord);
        }

        $this->good('CUSTOM_DOMAIN_CNAME_TARGET', $cname !== '' ? $cname : ($host ?: 'unset'));
        $this->good('CUSTOM_DOMAIN_TXT_PREFIX', $txt);

        $legacy = (array) config('custom_domain.legacy_txt_verification_prefixes', []);

        if ($legacy !== []) {
            $this->line('    <fg=gray>Also accepted, never advertised: '.implode(', ', $legacy).'</>');
            $this->line('    <fg=gray>so a domain verified before the rename keeps working untouched.</>');
        }

        $this->newLine();

        // Everything Standard has, on the school's own domain instead of a
        // subdomain. Until the domain verifies, the school stays on its
        // Standard subdomain - it is never left with no address at all.
        $site = 'https://'.$this->hint('their-own-domain');

        $this->line('    Website           '.$site.'/');
        $this->line('    Portal hub        '.$site.'/portal');
        $this->line('    Staff sign-in     '.$site.'/staff-portal/'.$this->hint('token').'/login');
        $this->line('    Pupil sign-in     '.$site.'/portal/'.$this->hint('token').'/login');
        $this->line('    Parent sign-in    '.$site.'/parent-portal/'.$this->hint('token').'/login');
        $this->newLine();
        $this->line('    <fg=gray>Until the domain verifies, the school stays on its Standard subdomain.</>');
    }

    /**
     * Real schools, real addresses - the check that the settings above actually
     * produce something, rather than merely being present.
     */
    private function examples(): void
    {
        $schools = School::with('activeSubscription.plan')->take(3)->get();

        if ($schools->isEmpty()) {
            return;
        }

        $this->section('Worked examples', 'Generated from schools in this database, using the settings above');

        foreach ($schools as $school) {
            $plan = $school->activeSubscription?->plan?->key?->label() ?? 'no active plan';

            $this->line('    <options=bold>'.$school->name.'</> <fg=gray>('.$plan.')</>');

            // The front door, then the website separately - because on Basic
            // they are not the same thing and the website does not exist.
            // This used to print publicUrl('public.school-website') for every
            // school, which on Basic is an address that always answers 404:
            // the report was confirming a link that could not work.
            $this->line('      front door  '.$school->frontDoorUrl());

            $this->line('      website     '.(
                $school->websiteUrl() ?? '<fg=gray>none — not included on this plan</>'
            ));

            $this->line('      staff       '.$school->portalLoginUrl('staff'));
            $this->line('      results     '.$school->resultLinkUrl());
            $this->newLine();
        }
    }

    private function section(string $title, string $why): void
    {
        $this->newLine();
        $this->line("  <options=bold>{$title}</>");
        $this->line("  <fg=gray>{$why}</>");
        $this->newLine();
    }

    private function good(string $key, string $value): void
    {
        $this->line("    <fg=green>✓</> <options=bold>{$key}</>  {$value}");
    }

    private function optional(string $key, string $problem, string $fix): void
    {
        $this->warnings++;
        $this->line("    <fg=yellow>!</> <options=bold>{$key}</>  {$problem}");
        $this->line("      <fg=gray>{$fix}</>");
    }

    private function bad(string $key, string $problem, string $fix): void
    {
        $this->problems++;
        $this->line("    <fg=red>✗</> <options=bold>{$key}</>  {$problem}");
        $this->line("      <fg=gray>{$fix}</>");
    }

    private function hint(string $text): string
    {
        return "<fg=gray>{{$text}}</>";
    }
}
