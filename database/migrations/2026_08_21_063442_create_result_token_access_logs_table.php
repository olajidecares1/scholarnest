<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to redeem a result token, successful or not.
 *
 * The existing `result_checking_pin_usages` table only records successes: both
 * its pin and student columns are required, so an attempt with a token that
 * does not exist cannot be written there at all. That leaves the interesting
 * half unrecorded, somebody working through guesses looks exactly like
 * silence.
 *
 * This table takes every attempt, including the ones where nothing could be
 * identified, which is what makes "investigate suspicious access" possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_token_access_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Known from the URL even when the token is not, so a school can
            // always be shown attempts made against it.
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // All null when the token did not resolve to anything.
            $table->foreignId('result_checking_pin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('examination_id')->nullable()->constrained()->nullOnDelete();

            // Why the attempt succeeded or failed, the value of
            // App\Enums\ResultTokenAccessOutcome.
            $table->string('outcome', 40);

            // The SHA-256 of what was typed, never the token itself. Enough to
            // spot the same wrong value being tried repeatedly, useless to
            // anyone who reads this table.
            $table->char('token_hash_attempted', 64)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            // "Show this school its result-access activity."
            $table->index(['school_id', 'occurred_at']);

            // "Is this address working through guesses?"
            $table->index(['ip_address', 'occurred_at']);

            // "How many failures across the platform today?"
            $table->index(['outcome', 'occurred_at']);

            // "Who has looked at this student's result?"
            $table->index(['student_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_token_access_logs');
    }
};
