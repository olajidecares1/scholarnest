<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The label a Standard school's website lives at, with no hyphens in it.
 *
 *     vincent-martins-college.scholarnest.com.ng
 *     vincentmartinscollege.scholarnest.com.ng
 *
 * A COLUMN RATHER THAN A COMPUTED VALUE, and that is the whole reason this
 * migration exists instead of a one-line str_replace in the model. Two things
 * make it necessary:
 *
 *   Resolution has to match generation. Every request to a subdomain is looked
 *   up by that label, and a WHERE on a value computed in PHP cannot use an
 *   index - so the lookup would either be a full scan or would have to strip
 *   hyphens from every row to compare.
 *
 *   Removing hyphens creates collisions the slug never had. "Saint Mary" and
 *   "Saint-Mary" are distinct slugs and the same subdomain. Something has to
 *   settle that once, at creation, rather than leaving two schools pointing at
 *   one address.
 *
 * The slug stays exactly as it is. It is still the API's identifier, still what
 * the school finder searches, still what an ID card prefix is built from, and
 * still what a deletion audit entry names. Only the subdomain changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('subdomain', 63)->nullable()->unique()->after('slug');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['subdomain']);
            $table->dropColumn('subdomain');
        });
    }

    /**
     * Derived from the NAME, not from the slug.
     *
     * Stripping hyphens out of the slug would give the same answer almost
     * always, but not for a school whose slug already carries a collision
     * suffix - "saint-mary-2" would become "saintmary2", which is a worse
     * address than the one the name itself produces.
     *
     * Ordered by id so the result is deterministic: when two schools do
     * collide, the older one keeps the plain label.
     */
    private function backfill(): void
    {
        $taken = [];

        DB::table('schools')->orderBy('id')->select(['id', 'name', 'slug'])->chunkById(200, function ($schools) use (&$taken) {
            foreach ($schools as $school) {
                $subdomain = School::availableSubdomain($school->name ?: $school->slug, $taken);

                $taken[] = $subdomain;

                DB::table('schools')->where('id', $school->id)->update(['subdomain' => $subdomain]);
            }
        });
    }
};
