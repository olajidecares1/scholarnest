<?php

use App\Models\School;
use App\Services\DefaultClassSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * JSS 2 and JSS 3 take BST 1 (PHE & ICT), BST 2 (Basic Science &
 * Technology), RNV (Social Studies, CRS & Civic Education) and PVS (Agric &
 * Home Economics) as combined subjects AS WELL AS the separate subjects they
 * are made of (config/subjects.php, jss_2_3), because some schools score
 * them one way and some the other.
 *
 * Brings every school already on the platform in line, exactly as
 * `php artisan subjects:backfill-defaults` would: the combined subjects are
 * added to those classes and to this session's score sheets, next to the
 * separate ones they already have. A class already offering a combined
 * subject under another spelling keeps it rather than getting it twice.
 * Nothing is taken away, and no score is touched.
 *
 * Whichever form a school does not score may stay blank: it does not hold
 * a result back and is not printed on the report card (CombinedSubjects).
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
        // The lists live in config; editing them back and running
        // subjects:backfill-defaults restores the earlier subjects.
    }
};
