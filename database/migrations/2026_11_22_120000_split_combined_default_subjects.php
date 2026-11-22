<?php

use App\Models\School;
use App\Services\DefaultClassSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * The combined subjects are separate subjects now (config/subjects.php):
 *
 *   BST 1 (PHE & ICT)                            → PHE, ICT
 *   BST 2 (Basic Science & Technology), BST     → Basic Science, Basic Technology
 *   RNV (Social Studies, CRS & Civic Education) → Social Studies, CRS, Civic Education
 *   PVS (Agriculture & Home Economics)           → Agricultural Science, Home Economics
 *
 * This brings every school already on the platform in line on deploy, for
 * the School Admin, class teachers, students and parents alike:
 *
 *  - each class gets the separate subjects, never twice;
 *  - the combined subject comes off the class's list and off this session's
 *    examinations where nothing has been scored in it;
 *  - a combined subject that already has a score is left on its examination,
 *    so no result is lost.
 *
 * The same work as `php artisan subjects:backfill-defaults`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! School::query()->exists()) {
            return;
        }

        DefaultClassSubjects::backfillAll();
    }

    public function down(): void
    {
        // Left in place; putting the combined subjects back would take
        // subjects away from classes that may already have scores in them.
    }
};
