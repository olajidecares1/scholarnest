<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which of a class's subjects are platform defaults (the JSS subjects
 * in config/subjects.php), so they can be told apart from subjects a school
 * added itself, and a School Admin cannot remove them.
 *
 * Dated just before 2026_11_18_090000 so a fresh database has the column
 * before that migration fills JSS classes. A database that already ran that
 * one simply runs this as a pending migration; the defaults are marked by
 * 2026_11_20_090000_backfill_default_jss_subjects.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('subject_offerings', 'is_default')) {
            return;
        }

        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('subject_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('subject_offerings', 'is_default')) {
            Schema::table('subject_offerings', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
