<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * There is somewhere to write.
 *
 * The legal documents told people how to exercise rights that only exist if
 * somebody answers, "contact us to request deletion", "report a vulnerability
 * to", and then gave them "[TO BE PROVIDED]". The subscription confirmation
 * offered a "Contact Support" button with href="#". A promise of support with
 * no address behind it is worse than no promise, because the reader stops
 * looking for another route.
 */
const SUPPORT_EMAIL = 'support@akademicanest.com';

/**
 * Run the support-contact migration against the test database.
 */
function runSupportContactMigration(): void
{
    $path = database_path('migrations/2026_09_10_152721_add_support_contact_details.php');

    expect(file_exists($path))->toBeTrue();

    (require $path)->up();
}

describe('the documents that promise a contact now carry one', function () {
    test('every legal document with a contact slot gets the address', function () {
        $slots = [
            'cookies' => 'Questions about this policy: [TO BE PROVIDED].',
            'privacy' => 'Data protection contact: [TO BE PROVIDED].',
            'terms' => '| General enquiries | [TO BE PROVIDED] |',
            'data-retention' => 'Contact AkademicNest at [TO BE PROVIDED].',
            'security' => 'To report a vulnerability or a suspected incident: **[TO BE PROVIDED]**.',
            'school-responsibilities' => '| Support | [TO BE PROVIDED] |',
        ];

        foreach ($slots as $slug => $body) {
            DB::table('legal_documents')->updateOrInsert(
                ['slug' => $slug],
                ['title' => ucfirst($slug), 'body' => $body, 'version' => '1.0', 'is_published' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        runSupportContactMigration();

        foreach (array_keys($slots) as $slug) {
            expect(DB::table('legal_documents')->where('slug', $slug)->value('body'))
                ->toContain(SUPPORT_EMAIL)
                ->not->toContain('[TO BE PROVIDED]');
        }
    })->skip(fn () => ! Schema::hasTable('legal_documents'), 'no legal_documents table');

    test('placeholders that are not an email address are left alone', function () {
        // Nobody can invent a company registration number or a hosting
        // provider. A legal document that is confidently wrong is worse than
        // one that is visibly unfinished.
        DB::table('legal_documents')->updateOrInsert(['slug' => 'terms'], [
            'title' => 'Terms',
            'body' => "| Operator | [TO BE PROVIDED] |\n| Registration number | [TO BE PROVIDED] |\n| General enquiries | [TO BE PROVIDED] |",
            'version' => '1.0',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runSupportContactMigration();

        $body = DB::table('legal_documents')->where('slug', 'terms')->value('body');

        expect($body)->toContain('| Operator | [TO BE PROVIDED] |')
            ->and($body)->toContain('| Registration number | [TO BE PROVIDED] |')
            ->and($body)->toContain('| General enquiries | '.SUPPORT_EMAIL.' |');
    })->skip(fn () => ! Schema::hasTable('legal_documents'), 'no legal_documents table');

    test('the markdown source says the same thing as the rows', function () {
        // The rows are seeded from these files once and edited in the admin
        // screen after, so the two drift unless both are changed.
        foreach (glob(resource_path('legal/*.md')) as $file) {
            if (str_contains($file, 'README') || str_contains($file, 'LEGAL-REVIEW')) {
                continue;
            }

            $source = file_get_contents($file);

            if (str_contains($source, 'enquiries |') || str_contains($source, 'Questions about this policy')) {
                expect($source)->toContain(SUPPORT_EMAIL);
            }
        }
    });
});

describe('the settings that reach invoices and email', function () {
    test('the migration fills the addresses that were null', function () {
        DB::table('settings')->delete();
        DB::table('settings')->insert([
            'site_name' => 'AkademicNest',
            'support_email' => null,
            'notification_from_email' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runSupportContactMigration();

        $settings = DB::table('settings')->first();

        expect($settings->support_email)->toBe(SUPPORT_EMAIL)
            ->and($settings->notification_from_email)->toBe(SUPPORT_EMAIL);
    });

    test('it does not overwrite an address somebody chose', function () {
        DB::table('settings')->delete();
        DB::table('settings')->insert([
            'site_name' => 'AkademicNest',
            'support_email' => 'help@a-school-chose-this.com',
            'notification_from_email' => 'help@a-school-chose-this.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        runSupportContactMigration();

        // A value set through the admin screen belongs to whoever set it.
        expect(DB::table('settings')->value('support_email'))->toBe('help@a-school-chose-this.com');
    });

    test('supportEmail falls back to the sending address rather than nothing', function () {
        DB::table('settings')->delete();

        config(['mail.from.address' => SUPPORT_EMAIL]);

        // Creates no row, it is read on a public page.
        expect(Setting::supportEmail())->toBe(SUPPORT_EMAIL)
            ->and(DB::table('settings')->count())->toBe(0);
    });
});

test('mail is not sent from the framework placeholder address', function () {
    // Shipped as "hello@example.com", the Laravel default. Every password
    // reset and every invoice claimed to come from example.com.
    expect(config('mail.from.address'))->not->toBe('hello@example.com')
        ->and(config('mail.from.address'))->toContain('@');
});

test('the config default is the support address, not example.com', function () {
    expect(file_get_contents(config_path('mail.php')))
        ->toContain("env('MAIL_FROM_ADDRESS', '".SUPPORT_EMAIL."')");
});
