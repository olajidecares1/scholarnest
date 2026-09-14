<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A memorandum knows who it was for.
 *
 * Notices went to students and only students, the audience was implicit in
 * the code that sent them. A school that wanted to tell its teachers something
 * had no way to, and one addressing everybody would have had to write the same
 * memo three times.
 *
 * Existing notices are backfilled as "students", which is who actually
 * received them. Recording what happened rather than what we would prefer had
 * happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_notices', function (Blueprint $table) {
            $table->string('audience', 20)->default('students')->after('body');
        });

        DB::table('school_notices')->update(['audience' => 'students']);
    }

    public function down(): void
    {
        Schema::table('school_notices', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
