<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A background photograph for the About section.
 *
 * The third of these, after the shared News & Events card and Academic
 * Excellence, and it sits beside them for the same reason: one more piece of a
 * school's website configuration on the row that already holds the rest.
 *
 * NOT the same thing as about_image_path, which is the photograph INSIDE the
 * section, the one in the frame beside the prose. This is the picture behind
 * the whole band. A school can set either, both or neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('about_card_image_path')->nullable()->after('academics_card_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn('about_card_image_path');
        });
    }
};
