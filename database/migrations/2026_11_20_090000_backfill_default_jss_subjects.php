<?php

use App\Models\School;
use App\Services\DefaultSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * Brings every school already on the platform in line with the JSS default
 * list in config/subjects.php, so `php artisan migrate --force` on deploy is
 * all it takes:
 *
 *  - adds Security Education, Christian Religious Studies and Islamic
 *    Religious Studies (and any other default a JSS class is missing),
 *    never twice, and never when the class already has the subject under
 *    another spelling;
 *  - marks every class's default subjects is_default;
 *  - takes the old combined "CRS/IRS" off JSS classes, now that the two are
 *    separate subjects. It stays in the catalogue, and past examinations and
 *    published results that used it are not touched.
 *
 * The same work as `php artisan subjects:backfill-jss`, which can be run
 * again at any time.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A brand-new database has no schools; its catalogue is filled by
        // the reference-data seeder, and each school by its registration.
        if (! School::query()->exists()) {
            return;
        }

        DefaultSubjects::backfillAll();
    }

    public function down(): void
    {
        // Offerings added here are left in place; removing them would take
        // subjects away from classes that may already have scores in them.
    }
};
