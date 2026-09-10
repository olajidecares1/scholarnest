<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The support address, everywhere the platform promised one and had none.
 *
 * TWO PLACES, both of which were empty:
 *
 *   settings          support_email and notification_from_email were null, so
 *                     subscription invoices printed no way to reply and mail
 *                     went out from the framework's "hello@example.com".
 *
 *   legal_documents   Six documents seeded with "[TO BE PROVIDED]" wherever a
 *                     contact belonged - a Privacy Policy that tells a parent
 *                     how to exercise their rights and then gives them nowhere
 *                     to write, a Security Statement inviting vulnerability
 *                     reports to an address that is not there.
 *
 * The markdown in resources/legal is the source of those documents, but the
 * rows were seeded once and are edited through the admin screen afterwards, so
 * the files and the rows have to be changed separately.
 *
 * ONLY THE CONTACT SLOTS ARE FILLED. The other placeholders in these documents
 * ask for facts nobody can invent - the operating company's registered name and
 * number, its address, the effective date, the hosting provider, whether a Data
 * Protection Officer is required. Guessing at those would produce a legal
 * document that is confidently wrong, which is worse than one that is visibly
 * unfinished. They stay as they are until somebody supplies them.
 *
 * Each replacement is a whole phrase rather than the bare placeholder, so a
 * "[TO BE PROVIDED]" that means something else is left alone, and running this
 * twice changes nothing the second time.
 */
return new class extends Migration
{
    private const EMAIL = 'support@akademicanest.com';

    /**
     * The exact phrases, matching resources/legal/*.md after the same edit.
     *
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $email = self::EMAIL;

        return [
            // Cookie policy
            'Questions about this policy: [TO BE PROVIDED].' => "Questions about this policy: {$email}.",

            // Privacy policy
            'Data protection contact: [TO BE PROVIDED].' => "Data protection contact: {$email}.",
            '| Privacy enquiries | [TO BE PROVIDED] |' => "| Privacy enquiries | {$email} |",

            // Terms
            '| General enquiries | [TO BE PROVIDED] |' => "| General enquiries | {$email} |",

            // Data retention
            'Contact AkademicNest at [TO BE PROVIDED].' => "Contact AkademicNest at {$email}.",
            '| Deletion and retention requests | [TO BE PROVIDED] |' => "| Deletion and retention requests | {$email} |",
            '| Data protection enquiries | [TO BE PROVIDED] |' => "| Data protection enquiries | {$email} |",

            // Security statement
            'To report a vulnerability or a suspected incident: **[TO BE PROVIDED]**.' => "To report a vulnerability or a suspected incident: **{$email}**.",
            '| Security reports | [TO BE PROVIDED] |' => "| Security reports | {$email} |",

            // School data processing framework
            '| Support | [TO BE PROVIDED] |' => "| Support | {$email} |",
        ];
    }

    public function up(): void
    {
        $this->fillSupportSettings();
        $this->fillLegalDocuments();
    }

    /**
     * The address the invoices print and the emails are sent from.
     *
     * Both columns were null, so subscription invoices went out with no way to
     * reply to them and every notification came from the framework's default
     * "hello@example.com". Only nulls are filled - an address somebody has
     * already set through the admin screen is theirs, not this migration's.
     */
    private function fillSupportSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (['support_email', 'notification_from_email'] as $column) {
            if (! Schema::hasColumn('settings', $column)) {
                continue;
            }

            DB::table('settings')
                ->whereNull($column)
                ->orWhere($column, '')
                ->update([$column => self::EMAIL]);
        }
    }

    private function fillLegalDocuments(): void
    {
        if (! Schema::hasTable('legal_documents')) {
            return;
        }

        $replacements = $this->replacements();

        DB::table('legal_documents')->orderBy('id')->chunkById(50, function ($rows) use ($replacements) {
            foreach ($rows as $row) {
                $changes = [];

                foreach (['summary', 'body'] as $column) {
                    if (! is_string($row->{$column} ?? null)) {
                        continue;
                    }

                    $updated = str_replace(array_keys($replacements), array_values($replacements), $row->{$column});

                    if ($updated !== $row->{$column}) {
                        $changes[$column] = $updated;
                    }
                }

                if ($changes !== []) {
                    DB::table('legal_documents')->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    /**
     * Deliberately empty. Putting "[TO BE PROVIDED]" back into a published
     * legal document, after people have been told where to write, helps
     * nobody.
     */
    public function down(): void {}
};
