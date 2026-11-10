<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The AkademicNest rename, for the copies that live in rows rather than files.
 *
 * Renaming the platform in the codebase does not touch these, because they were
 * written into the database at some earlier point and the code only reads them
 * back. This is why the browser tab, the invoices and the Terms all went on
 * saying the old name after the code had stopped.
 *
 *   settings          The site name, and the name notifications are sent from.
 *                     Printed on every subscription invoice.
 *
 *   legal_documents   Seeded from resources/legal and edited since through the
 *                     admin screen. The Terms name the operator dozens of
 *                     times; a school reading them would be agreeing with a
 *                     company that no longer exists under that name.
 *
 *   plans.features    The bullet list on the pricing and subscription pages,
 *                     which named the old brand in the subdomain example.
 *
 * BOTH old names are handled, not just the most recent one. A database restored
 * from a backup taken before the first rename has "EduNest" in it, and arriving
 * here by that route is no reason to be left half-renamed.
 *
 * WHAT IS DELIBERATELY LEFT ALONE
 *
 *   schools           A school's own website text, news posts and remarks
 *                     belong to the school. If one of them names the platform,
 *                     that is their sentence, and silently rewriting a
 *                     customer's words, in pages they may have had approved,
 *                     is not a rename, it is an edit nobody asked for.
 *
 *   audit_logs        A record of what happened, and what happened happened
 *                     under the old name. Rewriting history to match the
 *                     present is the one thing an audit log must never do.
 *
 *   notifications     Already delivered, and dated. Same reasoning.
 *
 *   migrations        The recorded identity of migrations that have already
 *                     run. Renaming one would make Laravel run it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceIn('settings', ['site_name', 'notification_from_name']);
        $this->replaceIn('legal_documents', ['title', 'summary', 'body']);

        // The plan bullets are a JSON array in a text column, so the same
        // string replacement works on the encoded form without decoding it.
        $this->replaceIn('plans', ['name', 'description', 'features']);

        $this->fixSubdomainExample();
    }

    /**
     * Deliberately empty.
     *
     * Reversing would put the old name back into documents that have since been
     * edited under the new one, and there is no way to tell those apart from
     * the untouched ones. The rollback of a rename is another rename.
     */
    public function down(): void {}

    /**
     * The Standard plan advertised "Subdomain (scholarnest.schoolname.com)",
     * which is the wrong way round as well as the wrong name, the school is
     * the label and the platform is the domain, not the other way about. What
     * the application actually issues is schoolname.akademicanest.com.
     */
    private function fixSubdomainExample(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        foreach (DB::table('plans')->get() as $plan) {
            if (! is_string($plan->features ?? null)) {
                continue;
            }

            // The production domain, written out rather than read from
            // config: this is sales copy a school reads before it has an
            // account, and it must not turn into "schoolname.lvh.me" because
            // the migration happened to run on somebody's laptop.
            $fixed = preg_replace(
                '/Subdomain \([A-Za-z0-9.-]+\)/i',
                'Subdomain (schoolname.akademicanest.com)',
                $plan->features,
            );

            if ($fixed !== null && $fixed !== $plan->features) {
                DB::table('plans')->where('id', $plan->id)->update(['features' => $fixed]);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function replaceIn(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Read, replace in PHP, write back, rather than a SQL REPLACE().
        // MySQL's REPLACE() is case sensitive and would need a pass per casing
        // per old name; and SQLite, which the tests run on, has no multi-pass
        // equivalent worth writing twice.
        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $changes = [];

                foreach ($columns as $column) {
                    if (! property_exists($row, $column) || ! is_string($row->{$column})) {
                        continue;
                    }

                    $updated = str_replace(
                        ['ScholarNest', 'SCHOLARNEST', 'scholarnest', 'EduNest', 'EDUNEST', 'edunest'],
                        ['AkademicNest', 'AKADEMICNEST', 'akademicnest', 'AkademicNest', 'AKADEMICNEST', 'akademicnest'],
                        $row->{$column},
                    );

                    if ($updated !== $row->{$column}) {
                        $changes[$column] = $updated;
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        });
    }
};
