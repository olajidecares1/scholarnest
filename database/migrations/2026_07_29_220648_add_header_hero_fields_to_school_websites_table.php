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
            $table->string('topbar_announcement')->nullable()->after('footer_text');
            $table->string('topbar_badge_text')->nullable()->after('topbar_announcement');
            $table->string('topbar_link_text')->nullable()->after('topbar_badge_text');
            $table->string('topbar_link_url')->nullable()->after('topbar_link_text');
            $table->string('cta_text')->nullable()->after('topbar_link_url');
            $table->string('cta_url')->nullable()->after('cta_text');
            $table->string('hero_secondary_text')->nullable()->after('cta_url');
            $table->string('hero_secondary_url')->nullable()->after('hero_secondary_text');
            $table->string('whats_happening_title')->nullable()->after('hero_secondary_url');
            $table->boolean('show_whats_happening')->default(true)->after('whats_happening_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn([
                'topbar_announcement',
                'topbar_badge_text',
                'topbar_link_text',
                'topbar_link_url',
                'cta_text',
                'cta_url',
                'hero_secondary_text',
                'hero_secondary_url',
                'whats_happening_title',
                'show_whats_happening',
            ]);
        });
    }
};
