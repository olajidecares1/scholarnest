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
            $table->text('admissions_intro')->nullable()->after('stats');
            $table->json('admissions_steps')->nullable()->after('admissions_intro');
            $table->json('admissions_requirements')->nullable()->after('admissions_steps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->dropColumn(['admissions_intro', 'admissions_steps', 'admissions_requirements']);
        });
    }
};
