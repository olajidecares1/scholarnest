<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each Class Teacher's own list of comments they have written before.
 *
 * Filled automatically as they write a comment on a pupil's report card, so
 * next time they can pick one instead of typing it out again. Belongs to the
 * teacher, not the school: one teacher's way of putting things is not
 * offered to another.
 *
 * Like the Principal's library, an entry is NOT a pupil's remark. Picking one
 * copies its text onto that pupil's own report, so tidying the list never
 * changes a card already written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_remarks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->text('body');

            // A hash, as on principal_remarks: a unique index on the text
            // itself would exceed InnoDB's key limit.
            $table->char('body_hash', 64);

            // Most recently used first in the list.
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'body_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_remarks');
    }
};
