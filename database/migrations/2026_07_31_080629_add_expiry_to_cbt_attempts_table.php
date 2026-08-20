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
        Schema::table('cbt_attempts', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('started_at');
            $table->boolean('auto_submitted')->default(false)->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_attempts', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'auto_submitted']);
        });
    }
};
