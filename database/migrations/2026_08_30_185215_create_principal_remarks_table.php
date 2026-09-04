<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Principal's library of reusable remarks.
 *
 * A head writes the same handful of sentences several hundred times a term -
 * "Outstanding performance. Keep up the good work." does not need typing out
 * once per pupil. These are the ones they have chosen to keep.
 *
 * A library entry is NOT a student's remark. What lands on a report card is
 * copied onto that pupil's own examination report, so editing a library entry
 * later never rewrites results already issued: a remark on a card is a thing
 * somebody said about a particular child at a particular moment, not a
 * reference to a row that can change underneath it.
 *
 * Scoped to the school, with no limit on how many a Principal keeps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principal_remarks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Who wrote it. Kept for the audit trail rather than for access:
            // the library belongs to the school, so a second administrator
            // can use and tidy what a first one saved.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            // A hash rather than the text itself, because a unique index on a
            // 1000-character utf8mb4 column exceeds InnoDB's key limit. Saving
            // the same sentence twice is a no-op instead of quietly filling
            // the list with duplicates nobody can tell apart.
            $table->char('body_hash', 64);
            $table->timestamps();

            $table->unique(['school_id', 'body_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('principal_remarks');
    }
};
