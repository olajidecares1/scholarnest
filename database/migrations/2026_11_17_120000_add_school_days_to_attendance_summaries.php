<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual term attendance, part two: the number of school days can be typed
 * per student, beside the days present and absent.
 *
 * Empty means "the term's school days" (Academics > Terms, or the weekdays
 * between the term's dates), which is what most pupils need. A figure is for
 * the pupil whose term was shorter than the school's: one who joined late,
 * left early, or transferred in. Their percentage is worked out against their
 * own school days rather than the whole term's.
 *
 * Nullable and additive: every row saved before this keeps meaning exactly
 * what it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_days')->nullable()->after('class_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('school_days');
        });
    }
};
