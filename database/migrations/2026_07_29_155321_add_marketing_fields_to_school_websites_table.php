<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->string('slogan')->nullable()->after('about_text');
            $table->string('slogan_tagline')->nullable()->after('slogan');
            $table->string('principal_name')->nullable()->after('slogan_tagline');
            $table->string('principal_title')->nullable()->after('principal_name');
            $table->text('principal_message')->nullable()->after('principal_title');
            $table->string('principal_photo_path')->nullable()->after('principal_message');
            $table->text('quote_text')->nullable()->after('principal_photo_path');
            $table->string('quote_author')->nullable()->after('quote_text');
            $table->string('quote_author_role')->nullable()->after('quote_author');
            $table->string('campus_video_url')->nullable()->after('quote_author_role');
            $table->json('stats')->nullable()->after('campus_video_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn([
                'slogan',
                'slogan_tagline',
                'principal_name',
                'principal_title',
                'principal_message',
                'principal_photo_path',
                'quote_text',
                'quote_author',
                'quote_author_role',
                'campus_video_url',
                'stats',
            ]);
        });
    }
};
