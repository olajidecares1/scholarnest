<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rename, for the copies that live in the database rather than in files.
 *
 * Renaming the platform in the codebase does not touch three things, because
 * they were written into rows at some earlier point and the code only reads
 * them back:
 *
 *   settings          The site name and the name notifications are sent from.
 *                     Both defaulted to "EduNest" and were stored, so every
 *                     page header and every email still says it.
 *
 *   legal_documents   Seeded from resources/legal and edited since through the
 *                     admin screen. The Terms name the operator roughly forty
 *                     times; a school reading them would still be agreeing with
 *                     EduNest.
 *
 *   schools           Only where a school typed the platform's name into its
 *                     own content, which is nobody's business to rewrite, so
 *                     schools are deliberately NOT touched. See below.
 *
 * SCHOOL-OWNED CONTENT IS LEFT ALONE. A school's website text, news posts and
 * remarks belong to the school. If one of them mentions EduNest by name that is
 * their sentence, and silently rewriting a customer's own words, in documents
 * they may have had approved, is not a rename, it is an edit nobody asked for.
 * The schools that care will change it themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceIn('settings', ['site_name', 'notification_from_name']);

        if (Schema::hasTable('legal_documents')) {
            $this->replaceIn('legal_documents', ['title', 'summary', 'body']);
        }
    }

    /**
     * Deliberately empty.
     *
     * Reversing would put the old name back into documents that have since been
     * edited under the new one, and there is no way to tell those apart from
     * the untouched ones. A rollback of a rename is a new rename.
     */
    public function down(): void {}

    /**
     * @param  list<string>  $columns
     */
    private function replaceIn(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Read, replace in PHP, write back, rather than a SQL REPLACE().
        // MySQL's REPLACE() is case sensitive and would need three passes for
        // "EduNest", "edunest" and "EDUNEST"; and SQLite, which the tests run
        // on, has no multi-pass equivalent worth writing twice.
        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns) {
            foreach ($rows as $row) {
                $changes = [];

                foreach ($columns as $column) {
                    if (! property_exists($row, $column) || ! is_string($row->{$column})) {
                        continue;
                    }

                    $updated = str_replace(
                        ['EduNest', 'EDUNEST', 'edunest'],
                        ['ScholarNest', 'SCHOLARNEST', 'scholarnest'],
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
