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
            $table->json('hero_layout')->nullable()->after('show_whats_happening');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn('hero_layout');
        });
    }
};
