<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('portal_admin_token', 64)->unique()->nullable()->after('slug');
            $table->string('portal_staff_token', 64)->unique()->nullable()->after('portal_admin_token');
            $table->string('portal_student_token', 64)->unique()->nullable()->after('portal_staff_token');
            $table->string('portal_guardian_token', 64)->unique()->nullable()->after('portal_student_token');
        });

        // Backfill every school that already exists, new schools get theirs
        // from the "creating" model event, but that never runs for rows
        // created before this migration.
        DB::table('schools')->orderBy('id')->get(['id'])->each(function ($school) {
            DB::table('schools')->where('id', $school->id)->update([
                'portal_admin_token' => Str::random(40),
                'portal_staff_token' => Str::random(40),
                'portal_student_token' => Str::random(40),
                'portal_guardian_token' => Str::random(40),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['portal_admin_token', 'portal_staff_token', 'portal_student_token', 'portal_guardian_token']);
        });
    }
};
