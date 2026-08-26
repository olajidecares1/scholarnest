<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's Result Repository: results a school has finished with and
 * published, kept apart from the marks that are still being entered.
 *
 * Until now a result token opened whatever the scores table said at the moment
 * the parent looked. There was no line between "the teacher is halfway through
 * entering Mathematics" and "the school has approved this child's report card",
 * so a parent could open a card, see it change an hour later, and be right to
 * ask which one was real.
 *
 * A row here IS that line. It carries a snapshot of the card as it was
 * approved, so what a parent reads is what the school signed off, and a later
 * correction reaches them only when somebody pushes it again.
 *
 * Keyed by school + student + class + session + term, unique. That is the
 * school's own idea of a result - one report card per child per term - and the
 * uniqueness is what makes a second push an update rather than a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_results', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Cascades, because a repository entry is meaningless without the
            // school it belongs to, and a deleted school must not leave its
            // pupils' report cards behind.
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Which examination produced it. Not part of the key - the key is
            // the term - but kept so an entry can always be traced back to the
            // marks it came from. Nulled rather than deleted if the
            // examination is removed: the published card outlives it.
            $table->foreignId('examination_id')->nullable()->constrained()->nullOnDelete();

            $table->string('class_name');
            $table->string('session');
            $table->string('term', 20);

            // The card as approved. Every figure on it - subjects, test and
            // exam scores, totals, grades, remarks, attendance, position -
            // read from the student's records at the moment of the push and
            // written down, so what a parent sees cannot drift underneath them.
            $table->json('payload');

            // A hash of the marks and remarks this snapshot was taken from.
            // Comparing it with the same hash computed now is how the school
            // is told "this has been corrected since you published it" without
            // rebuilding every card to find out.
            $table->char('source_fingerprint', 64);

            // Who pushed it, and under what name. The name is stored rather
            // than joined because a teacher can leave, and an audit trail that
            // forgets who published a child's result is not one.
            $table->nullableMorphs('pushed_by');
            $table->string('pushed_by_name');
            $table->timestamp('pushed_at');

            // Starts at 1 and increments on every repush, so "this card has
            // been corrected twice" is a fact the school can read.
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // The rule from the brief, made a property of the table rather
            // than of the code that writes to it: one published result per
            // child per class per session per term.
            $table->unique(
                ['school_id', 'student_id', 'class_name', 'session', 'term'],
                'repository_results_unique_result',
            );

            // How the School Admin browses: class, then session, then term.
            $table->index(['school_id', 'class_name', 'session', 'term'], 'repository_results_browse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_results');
    }
};
