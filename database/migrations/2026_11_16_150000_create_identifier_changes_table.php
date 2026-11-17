<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regenerating admission numbers and Staff IDs after a school code is set.
 *
 * identifier_changes is the record of every number a student or staff member
 * used to have. A receipt, report card or letter printed before the change
 * still carries the old number, so it has to stay traceable to the person:
 * the school can search by it, and the person can still sign in with it.
 *
 * schools.previous_school_codes remembers the codes a school has used before,
 * so numbers generated under an old code are still recognised as generated
 * (and so replaceable) rather than mistaken for numbers typed by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identifier_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // 'admission' for a student, 'staff' for a staff member.
            $table->string('type', 16);
            $table->unsignedBigInteger('holder_id');

            $table->string('old_value', 64);
            $table->string('new_value', 64);

            // Every change made by one regeneration shares a batch, so the
            // school can see what one press of the button did.
            $table->uuid('batch');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'type', 'old_value']);
            $table->index(['school_id', 'type', 'holder_id']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->json('previous_school_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('previous_school_codes');
        });

        Schema::dropIfExists('identifier_changes');
    }
};
