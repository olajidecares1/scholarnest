<?php

use App\Models\School;
use App\Services\DefaultSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * The 17 Junior Secondary (JSS) subjects are now ticked by default.
 *
 * New schools get them when they register. This brings schools that were
 * already on the platform in line: the 17 are added to the catalogue, and
 * every JSS class gets whichever of the 17 it does not offer yet. Nothing
 * a school already ticked is removed, and an admin can still untick a
 * subject the school does not run from the Class Subjects page.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A brand-new database has no schools yet; its catalogue (the 17
        // included) is filled by the reference-data seeder instead.
        if (! School::query()->exists()) {
            return;
        }

        // Already run on every database that had schools; kept working
        // against the current defaults (config/subjects.php).
        DefaultSubjects::backfillAll();
    }

    public function down(): void
    {
        // Offerings added here are indistinguishable from ones a school
        // ticked itself, so they are left in place.
    }
};
