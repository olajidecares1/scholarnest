<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card's third colour, which a school could not change.
 *
 * The reference design carries a red as well as the two colours a school could
 * already set: the stripe under the masthead, the tagline, the holder's badge
 * and the contact icons on the back are all it. That red was written into the
 * card templates as a constant, on the reasoning that it was part of the
 * design rather than a school setting.
 *
 * It is not: this design is the DEFAULT that every school edits into its own,
 * and a school whose colours are green and gold should not be left with a red
 * stripe it has no way to change.
 *
 * Nullable, and null means the reference red. A school that has never opened
 * the editor keeps exactly the card it has today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->string('accent_color', 20)->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->dropColumn('accent_color');
        });
    }
};
