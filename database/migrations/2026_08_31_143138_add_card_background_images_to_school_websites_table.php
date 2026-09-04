<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Background images for two cards on the public website.
 *
 * TWO columns, not three. Latest News and Upcoming Events share ONE background
 * - they are one card as far as a school is concerned, and giving them a
 * setting each would say they were two. Academic Excellence is a separate card
 * and gets its own.
 *
 * They live on school_websites beside hero_image_path and about_image_path
 * rather than in a table of their own: this is one more piece of a school's
 * website configuration, and it already has a row here.
 *
 * Nullable, and the website falls back to its existing flat background when
 * they are - which is what every school has today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('news_events_card_image_path')->nullable()->after('about_image_path');
            $table->string('academics_card_image_path')->nullable()->after('news_events_card_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn(['news_events_card_image_path', 'academics_card_image_path']);
        });
    }
};
