<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The school's identifier inside a portal address, in place of its slug.
 *
 * Portal URLs used to carry the school's public slug:
 *
 *     /schools/greenfield-college/staff-portal/{token}/login
 *
 * which named the school in every link a teacher, pupil or parent ever
 * received, in every bookmark, and in every referrer header those pages sent.
 * The slug is not a secret - it is on the public website - but there is no
 * reason for a private portal address to announce whose portal it is.
 *
 * This column replaces it:
 *
 *     /p/8Kq2mVx7RbnT4wLpZs3H/staff-portal/{token}/login
 *
 * It is NOT a second password. The portal token already gates the sign-in page
 * and the session already gates everything behind it; this only stops the URL
 * naming the school. Treating it as a secret would be a mistake - it is an
 * opaque handle, and the security is elsewhere.
 *
 * Separate from the four portal tokens on purpose. Those are per portal and
 * exist to keep sign-in pages off the open web; this one is per school and
 * shared by all four, so a school has one address family rather than four
 * unrelated ones.
 */
return new class extends Migration
{
    /**
     * Long enough not to be enumerable, short enough to survive being pasted
     * into a message without wrapping.
     */
    private const LENGTH = 20;

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('portal_key', 32)->nullable()->unique()->after('slug');
        });

        // Backfilled rather than defaulted: every existing school needs one
        // immediately, because its portal routes stop resolving without it.
        DB::table('schools')->orderBy('id')->select('id')->chunkById(200, function ($schools) {
            foreach ($schools as $school) {
                DB::table('schools')
                    ->where('id', $school->id)
                    ->update(['portal_key' => Str::random(self::LENGTH)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['portal_key']);
            $table->dropColumn('portal_key');
        });
    }
};
