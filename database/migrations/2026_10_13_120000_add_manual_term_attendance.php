<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual term attendance: a teacher enters how many days each pupil was
 * present, absent, late and excused over a whole term, instead of (or as well
 * as) marking the daily register.
 *
 * Three things are added, and nothing existing is changed.
 *
 * 1. Two settings on the school. `manual_attendance_enabled` is OFF by
 *    default, so no school sees a difference until it turns it on, and it is
 *    a column on the school rather than a plan feature because it is
 *    available on every plan. `manual_attendance_teacher_edits` says whether
 *    a class teacher may correct totals that are already saved; when off,
 *    only the School Admin can.
 *
 * 2. `school_days` on a term: the number of days the school actually opened.
 *    Optional. When it is empty the weekdays between the term's start and end
 *    dates are used, which is right for most schools and wrong for any term
 *    with a public holiday, which is why it can be set.
 *
 * 3. `attendance_summaries`, one row per pupil per session per term. A table
 *    of its own, deliberately beside `attendance_records` and not inside it:
 *    the daily register and QR check-in keep writing exactly what they wrote
 *    before, and a school can use both without one overwriting the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('manual_attendance_enabled')->default(false)->after('cumulative_average_basis');
            $table->boolean('manual_attendance_teacher_edits')->default(true)->after('manual_attendance_enabled');
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_days')->nullable()->after('ends_on');
        });

        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('session', 20);
            $table->string('term', 10);

            // The class the pupil was in when the totals were entered, so a
            // pupil promoted mid-session keeps last term's totals under last
            // term's class.
            $table->string('class_name')->nullable();

            $table->unsignedSmallInteger('days_present')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('days_late')->default(0);
            $table->unsignedSmallInteger('days_excused')->default(0);
            $table->text('notes')->nullable();

            // Who last saved the row: a School Admin (users) or a teacher
            // (staff). The same pair the daily register uses.
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('marked_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            // One set of totals per pupil per term. Saving twice, or an
            // offline form replayed on reconnect, updates the same row.
            $table->unique(['student_id', 'session', 'term']);
            $table->index(['school_id', 'session', 'term', 'class_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn('school_days');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['manual_attendance_enabled', 'manual_attendance_teacher_edits']);
        });
    }
};
