<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The typeface a school's website is set in, and how heavy its body text is.
 *
 * Both nullable, and they stay that way: a school that has never opened the
 * typography controls has not chosen Inter at 400, it has chosen nothing, and
 * the difference matters the day those defaults change. The website falls back
 * when they are null rather than being backfilled with a guess.
 *
 * The FAMILY is a name, not a stylesheet URL - it is validated against the
 * curated list in config/website_fonts.php before it is stored, so nothing a
 * school types can put an arbitrary font host into every visitor's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('font_family')->nullable()->after('brand_secondary_color');
            $table->unsignedSmallInteger('font_weight')->nullable()->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn(['font_family', 'font_weight']);
        });
    }
};
