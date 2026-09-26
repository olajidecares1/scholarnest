<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Release 1.2: the legal documents move from Version 1.1 to 1.2.
 *
 * The rows, not the markdown, are what schools read, so the bump has to reach
 * them. Only a document still at 1.1 is touched: one the AkademicNest Team has
 * already re-versioned through the admin screen is theirs, and a migration
 * that overwrote it would be rewriting a decision somebody made on purpose.
 *
 * The version line at the head of the body is updated alongside the column,
 * so the page never shows "Version 1.2" in its header and "Version 1.1" in its
 * first paragraph.
 */
return new class extends Migration
{
    private const FROM = '1.1';

    private const TO = '1.2';

    public function up(): void
    {
        $this->bump(self::FROM, self::TO, '13 September 2026', '26 September 2026');
    }

    public function down(): void
    {
        $this->bump(self::TO, self::FROM, '26 September 2026', '13 September 2026');
    }

    private function bump(string $from, string $to, string $fromDate, string $toDate): void
    {
        if (! Schema::hasTable('legal_documents')) {
            return;
        }

        DB::table('legal_documents')->where('version', $from)->orderBy('id')->chunkById(50, function ($rows) use ($from, $to, $fromDate, $toDate) {
            foreach ($rows as $row) {
                $body = preg_replace(
                    '/^\*\*Version '.preg_quote($from, '/').' · (.*?)Last updated '.preg_quote($fromDate, '/').'\*\*$/mu',
                    '**Version '.$to.' · $1Last updated '.$toDate.'**',
                    (string) $row->body,
                    1,
                );

                DB::table('legal_documents')->where('id', $row->id)->update([
                    'version' => $to,
                    'body' => $body ?? $row->body,
                ]);
            }
        });
    }
};
