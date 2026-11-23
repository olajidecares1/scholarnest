<?php

use App\Models\School;
use App\Services\DefaultClassSubjects;
use Illuminate\Database\Migrations\Migration;

/**
 * Primary (Basic) 4 and 5 get their own default subjects, as the school's
 * list has them: Mathematics, English, BST, RNV, CCA, History, Phonics,
 * Music, French, Computer, Yoruba, Igbo, Quantitative Reasoning, Verbal
 * Reasoning and Handwriting (config/subjects.php, primary_4_5).
 *
 * Brings every school already on the platform in line, exactly as
 * `php artisan subjects:backfill-defaults` would: BST, RNV, Yoruba and Igbo
 * are added to those classes, and the platform subjects they no longer take
 * (Basic Science, Basic Technology, Social Studies, CRS, Civic Education,
 * Yoruba / Igbo) come off their subject lists and off this session's score
 * sheets where NO score has been entered. A subject with a score in it is
 * never touched, and nothing a school added itself is removed.
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
