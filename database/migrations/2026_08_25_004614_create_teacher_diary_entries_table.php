<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each teacher taught, week by week.
 *
 * One row is one topic, for one subject, in one class, in one week of one term
 * of one session, by one teacher, at one school. Every one of those is a
 * column rather than something inferred later, because the record is only
 * useful if it can be read back along any of them, "what did 2A cover in
 * Mathematics last term" is the question this exists to answer.
 *
 * The unique key is what stops a week being logged twice for the same subject:
 * a teacher revising what they wrote is editing that week's entry, not adding
 * a second one. It deliberately excludes the school, because staff_id already
 * belongs to exactly one school.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Columns and indexes first, foreign keys after.
        //
        // MySQL refuses to add a unique index beginning with staff_id in the
        // same pass that creates the foreign key on it: the new index looks
        // like a replacement for the one the constraint is relying on, and the
        // alter fails with errno 150. Creating the index first and pointing
        // the constraint at the finished table avoids the ambiguity entirely.
        Schema::create('teacher_diary_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('staff_id');

            // Sized to what they hold rather than left at the default 255.
            // All four sit in the composite unique below, and at utf8mb4's four
            // bytes per character four 255-column keys blow past InnoDB's
            // 3072-byte index limit, which surfaces as a foreign key that
            // "is incorrectly formed" rather than as anything about length.
            $table->string('class_name', 100);
            $table->string('subject', 100);
            $table->string('session', 20);
            $table->string('term', 20);
            $table->unsignedTinyInteger('week_number');

            $table->text('topic');

            // "submitted" until a School Admin has read it, then "seen". Who
            // read it and when are kept because "the school has seen this" is
            // a claim the teacher is shown, and a claim with nobody's name on
            // it is worth less.
            $table->string('status', 20)->default('submitted');
            $table->unsignedBigInteger('seen_by')->nullable();
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['staff_id', 'class_name', 'subject', 'session', 'term', 'week_number'],
                'diary_entry_week_unique',
            );
            // Its own index, so the foreign key never has to borrow the
            // leftmost prefix of the composite unique above.
            $table->index('staff_id');
            $table->index(['school_id', 'session', 'term']);
            $table->index(['school_id', 'status']);
        });

        Schema::table('teacher_diary_entries', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('seen_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_diary_entries');
    }
};
