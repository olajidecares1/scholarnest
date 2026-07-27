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
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('login_background_media_id')->nullable()->after('favicon_path')->constrained('media')->nullOnDelete();
            $table->foreignId('register_background_media_id')->nullable()->after('login_background_media_id')->constrained('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('login_background_media_id');
            $table->dropConstrainedForeignId('register_background_media_id');
        });
    }
};
