<?php

use App\Enums\PlanKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Basic schools may create as many teacher accounts as they need.
 *
 * The Basic plan is sold per student, and the licence it buys is a student
 * licence. Capping teachers on top of that charged a school twice for the same
 * thing: a 300-student school that employs 40 teachers was told to upgrade, not
 * because it wanted a website or CBT, but because it had staff.
 *
 * Nothing is special-cased in code for this. `max_teachers` is already the
 * generic per-plan cap and null already means "no cap", Standard and Exclusive
 * have always been null, so Basic simply joins them, and the enforcement in
 * the staff controller is left exactly as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')->where('key', PlanKey::Basic->value)->update([
            'max_teachers' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('plans')->where('key', PlanKey::Basic->value)->update([
            'max_teachers' => 10,
        ]);
    }
};
