<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a school says about itself on its own front page.
 *
 * The About section had one field - a block of prose - and the design calls
 * for five things: a headline, a photograph, and the three statements a
 * school is actually asked for when a parent is deciding.
 *
 * Mission, vision and values are separate columns rather than one blob,
 * because they are three different questions and a school answers them
 * separately. Storing them together would mean parsing them apart to lay them
 * out in three columns, which is the kind of guess that goes wrong the first
 * time somebody uses a different heading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('about_headline')->nullable()->after('about_text');
            $table->string('about_image_path')->nullable()->after('about_headline');
            $table->text('mission')->nullable()->after('about_image_path');
            $table->text('vision')->nullable()->after('mission');
            $table->text('values')->nullable()->after('vision');
        });
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn(['about_headline', 'about_image_path', 'mission', 'vision', 'values']);
        });
    }
};
