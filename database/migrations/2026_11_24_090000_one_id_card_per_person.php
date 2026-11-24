<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One ID card record per person, enforced by the database.
 *
 * The existing unique key was (school, card type, holder), so a staff member
 * who moved between teaching and non-teaching could be given a second card.
 * This makes it (school, holder): the card follows the person whatever their
 * type (App\Models\IssuedIdCard::issueFor).
 *
 * Any person who already has two is left with one: their active card if
 * they have one, otherwise the most recently issued, and of those the
 * newest. The others, superseded copies of the same person's card, are
 * removed. No school is otherwise touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('issued_id_cards')
            ->select('school_id', 'holder_uuid')
            ->groupBy('school_id', 'holder_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $keep = DB::table('issued_id_cards')
                ->where('school_id', $group->school_id)
                ->where('holder_uuid', $group->holder_uuid)
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('issued_id_cards')
                ->where('school_id', $group->school_id)
                ->where('holder_uuid', $group->holder_uuid)
                ->where('id', '!=', $keep)
                ->delete();
        }

        Schema::table('issued_id_cards', function (Blueprint $table) {
            $table->unique(['school_id', 'holder_uuid'], 'issued_id_cards_one_per_holder');
        });
    }

    public function down(): void
    {
        Schema::table('issued_id_cards', function (Blueprint $table) {
            $table->dropUnique('issued_id_cards_one_per_holder');
        });
    }
};
