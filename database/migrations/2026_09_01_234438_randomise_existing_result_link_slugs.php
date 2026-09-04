<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Existing result-checking links stop naming their school.
 *
 * These were generated from the school's name - "greenfield-college" - and it
 * is the one link a school deliberately spreads: into messages to parents,
 * onto notice boards, into browser histories and referrer headers. Schools
 * created from now on get a random one; this gives the same to the schools
 * that already exist.
 *
 * EVERY EXISTING RESULT LINK STOPS WORKING. That is the point of the change
 * and not a side effect: an old link that still resolved would still name the
 * school. Schools must re-share the new link, which they can find on their
 * result-tokens screen.
 *
 * The old slugs are NOT recorded as retired. RetiredSchoolResultLink exists to
 * stop a burnt slug being handed to another school later, and a name-derived
 * slug could only ever be re-derived from the same name - which no longer
 * happens now that generation is random. Writing them would preserve, in a
 * table, exactly the school names this migration exists to remove.
 */
return new class extends Migration
{
    private const LENGTH = 16;

    public function up(): void
    {
        DB::table('schools')->orderBy('id')->select('id')->chunkById(200, function ($schools) {
            foreach ($schools as $school) {
                DB::table('schools')
                    ->where('id', $school->id)
                    ->update(['result_link_slug' => Str::random(self::LENGTH)]);
            }
        });
    }

    /**
     * Deliberately empty.
     *
     * There is nothing to restore to. The previous slugs were derived from
     * school names and were not kept, and regenerating them here would put the
     * names back into the URLs this migration removed them from.
     */
    public function down(): void {}
};
