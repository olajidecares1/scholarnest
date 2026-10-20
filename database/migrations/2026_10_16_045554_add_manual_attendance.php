<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual attendance: a term's totals entered per student, as an optional
 * alternative to the daily register.
 *
 * Kept in its own table rather than written into attendance_records, so the
 * daily register is never touched by it: a school that switches manual entry
 * off is left with exactly the register it had.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('manual_attendance_enabled')->default(false)->after('automatic_grading');
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_days')->nullable()->after('ends_on');
        });

        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('class_name')->nullable();
            $table->string('session');
            $table->string('term', 20);
            $table->unsignedSmallInteger('days_present')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('days_late')->default(0);
            $table->unsignedSmallInteger('days_excused')->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'session', 'term']);
            $table->index(['school_id', 'session', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn('school_days');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('manual_attendance_enabled');
        });
    }
};
