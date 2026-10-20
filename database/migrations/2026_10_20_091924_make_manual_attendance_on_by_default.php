<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual attendance is now on for every school unless the Super Admin turns
 * it off for one, rather than off until a School Admin turns it on.
 *
 * Every existing school is switched on as well: until now the switch belonged
 * to School Admins, who no longer control it, so no school's current value
 * reflects a Super Admin's decision.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('manual_attendance_enabled')->default(true)->change();
        });

        DB::table('schools')->update(['manual_attendance_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('manual_attendance_enabled')->default(false)->change();
        });
    }
};
