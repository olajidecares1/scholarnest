<?php

use App\Support\ReferenceData;
use Illuminate\Database\Migrations\Migration;

/**
 * Put the plans, the subject list and the CBT catalogue into a database that
 * has none - which is every production database, because a deploy runs
 * migrations and never seeders. See App\Support\ReferenceData.
 *
 * Only empty catalogues are touched, so this is safe on a database that was
 * seeded by hand and has been edited since.
 *
 * NOT IN THE TEST SUITE. Every test builds the data it needs from an empty
 * database; filling every test database with plans and subjects here would
 * change the ground all of them stand on. ReferenceData is tested directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        ReferenceData::seedMissing();
    }

    public function down(): void
    {
        // Nothing to undo: the rows may have been edited, or subscribed to,
        // since they were created.
    }
};
