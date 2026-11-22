<?php

use App\Models\School;
use App\Services\DefaultClassSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives every school already on the platform the default subjects for all
 * its classes (config/subjects.php): Reception, Nursery 1 and 2, Primary,
 * JSS 1, JSS 2 and 3, and SS 1 to 3 by department. So `php artisan migrate
 * --force` on deploy is all it takes:
 *
 *  - adds each class's default subjects, never twice, and never when the
 *    class already has the subject under another spelling;
 *  - marks them is_default, so a School Admin cannot remove them;
 *  - takes off the old JSS defaults that are no longer on a class's list
 *    (scores are kept: they belong to the examination);
 *  - brings this session's examinations in line, so every subject is on
 *    the teachers' score sheets.
 *
 * The same work as `php artisan subjects:backfill-defaults`, which can be
 * run again at any time.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A brand-new database has no schools; each school gets its
        // subjects when it registers.
        if (! School::query()->exists()) {
            return;
        }

        DefaultClassSubjects::backfillAll();
    }

    public function down(): void
    {
        // Left in place; removing them would take subjects away from
        // classes that may already have scores in them.
    }
};
