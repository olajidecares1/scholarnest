<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The headline and subline for the band above the footer.
 *
 * cta_text and cta_url were already here, but they are the BUTTON - its label
 * and where it goes. The band's own words had nowhere to live, so it was drawn
 * from positioned website-builder blocks squeezed into a fixed 90px box, which
 * is why it rendered as overlapping text. Two plain columns and the band can be
 * laid out properly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('cta_title')->nullable()->after('cta_url');
            $table->string('cta_subtitle')->nullable()->after('cta_title');
        });
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn(['cta_title', 'cta_subtitle']);
        });
    }
};
