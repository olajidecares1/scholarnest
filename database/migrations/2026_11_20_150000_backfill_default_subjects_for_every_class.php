<?php

use App\Models\School;
use App\Services\DefaultSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * Brings every school already on the platform in line with the default
 * subject lists in config/subjects.php, now that they cover Reception/KG,
 * Nursery 1, Nursery 2, Primary, JSS 1, JSS 2–3 and SS (with the Science,
 * Art and Commercial streams), so `php artisan migrate --force` on deploy is
 * all it takes:
 *
 *  - adds each default a class is missing, never twice, and never when the
 *    class already has the subject under another spelling;
 *  - marks every class's default subjects is_default;
 *  - takes off a class any subject that was a default before and is not on
 *    its list now, unless a score has been recorded for it there; then it
 *    stays, as the school's own subject.
 *
 * The same work as `php artisan subjects:backfill`, which can be run again
 * at any time.
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
