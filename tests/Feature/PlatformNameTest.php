<?php

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform is called AkademicNest, in the database as well as in the code.
 *
 * The last rename left the old name in the browser tab, because the name a page
 * prints does not come from the codebase at all, it comes from APP_NAME, and
 * from rows written into `settings` and `legal_documents` long before. Renaming
 * every string in every file changed none of them.
 *
 * Worse, that rename ran over the PREVIOUS rename's own migration and turned
 * its str_replace into an identity map, so the one piece of machinery built to
 * fix stored copies quietly stopped doing anything. Nothing failed; it just
 * silently stopped working.
 *
 * These tests hold both ends: the configured name, and the migration that moves
 * the stored ones.
 */

/**
 * Run the stored-content rename against the test database.
 */
function runPlatformRename(): void
{
    $path = database_path('migrations/2026_09_10_151016_rename_scholarnest_to_akademicnest_in_stored_content.php');

    expect(file_exists($path))->toBeTrue();

    (require $path)->up();
}

test('the configured platform name is AkademicNest', function () {
    expect(config('app.name'))->toBe('AkademicNest');
});

test('a deployment that forgets APP_NAME still says AkademicNest', function () {
    // The fallback matters as much as the value, without it a fresh server
    // greets its first school as "Laravel".
    $config = file_get_contents(config_path('app.php'));

    expect($config)->toContain("env('APP_NAME', 'AkademicNest')");
});

test('no layout hardcodes an older name in its title', function () {
    foreach (glob(resource_path('views/components/*layout*.blade.php')) as $layout) {
        $source = file_get_contents($layout);

        expect($source)->not->toContain('ScholarNest')
            ->and($source)->not->toContain('EduNest')
            // "AkademicNest AkademicNest Team", what a blind find-and-replace
            // over an already-branded string produces.
            ->and($source)->not->toContain('AkademicNest AkademicNest');
    }
});

describe('the stored copies of the name', function () {
    test('it renames the settings that reach invoices and emails', function () {
        DB::table('settings')->insert([
            'site_name' => 'ScholarNest',
            'notification_from_name' => 'EduNest',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runPlatformRename();

        $settings = DB::table('settings')->first();

        // Both older names, not only the most recent: a database restored from
        // an old backup arrives carrying either one.
        expect($settings->site_name)->toBe('AkademicNest')
            ->and($settings->notification_from_name)->toBe('AkademicNest');
    });

    test('it renames the legal documents a school agrees to', function () {
        DB::table('legal_documents')->updateOrInsert(['slug' => 'terms'], [
            'title' => 'ScholarNest Terms',
            'summary' => 'Your agreement with EduNest.',
            'body' => 'ScholarNest may suspend a SCHOLARNEST account.',
            'version' => '1.0',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runPlatformRename();

        $document = DB::table('legal_documents')->where('slug', 'terms')->first();

        expect($document->title)->toBe('AkademicNest Terms')
            ->and($document->summary)->toBe('Your agreement with AkademicNest.')
            ->and($document->body)->toBe('AkademicNest may suspend a AKADEMICNEST account.');
    })->skip(fn () => ! Schema::hasTable('legal_documents'), 'no legal_documents table');

    test('it renames the plan copy on the pricing page', function () {
        $plan = Plan::factory()->create([
            'features' => ['ScholarNest support', 'Subdomain (scholarnest.schoolname.com)'],
        ]);

        runPlatformRename();

        expect($plan->refresh()->features)->toContain('AkademicNest support');
    });

    test('the subdomain example reads school-then-platform, not the reverse', function () {
        $plan = Plan::factory()->create([
            'features' => ['Subdomain (scholarnest.schoolname.com)'],
        ]);

        runPlatformRename();

        // "scholarnest.schoolname.com" had it backwards as well as misnamed,
        // the school is the label, the platform is the domain.
        expect($plan->refresh()->features)->toContain('Subdomain (schoolname.akademicanest.com)');
    });

    test('the domain in that copy is not whichever machine ran the migration', function () {
        // A laptop has TENANT_BASE_DOMAIN=lvh.me. Sales copy a school reads
        // before it has an account must not say "schoolname.lvh.me".
        config(['custom_domain.tenant_base_domain' => 'lvh.me']);

        $plan = Plan::factory()->create(['features' => ['Subdomain (scholarnest.schoolname.com)']]);

        runPlatformRename();

        expect(implode(' ', $plan->refresh()->features))->not->toContain('lvh.me');
    });

    test('it leaves history alone', function () {
        DB::table('audit_logs')->insert([
            'action' => 'login',
            'description' => 'Signed in to ScholarNest',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runPlatformRename();

        // An audit trail rewritten to match the present is not an audit trail.
        // What happened, happened under the old name.
        expect(DB::table('audit_logs')->value('description'))->toBe('Signed in to ScholarNest');
    })->skip(fn () => ! Schema::hasTable('audit_logs'), 'no audit_logs table');

    test('running it twice changes nothing the second time', function () {
        $plan = Plan::factory()->create(['features' => ['ScholarNest support']]);

        runPlatformRename();
        $once = $plan->refresh()->features;

        runPlatformRename();

        expect($plan->refresh()->features)->toBe($once);
    });
});
