<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's decision to release one student's results for one term.
 *
 * A result is withheld while fees are owed. That rule needs an exception the
 * school controls, a family on a payment plan, a bursary being processed, a
 * balance the office knows is wrong, and the exception has to be a record,
 * not a setting: who released it, when, and why, per student and per term.
 *
 * Scoped to a session and a term rather than to an examination, because that
 * is the unit a bursar actually clears. A student cleared for Second Term
 * 2025/2026 is cleared for every examination in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_fee_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('session');
            $table->string('term');

            // Who took the decision. Kept even if that account is later
            // removed, because the release still happened.
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            // One live decision per student, per term. Releasing twice is the
            // same decision, not two.
            $table->unique(['student_id', 'session', 'term']);
            $table->index(['school_id', 'session', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_fee_clearances');
    }
};
