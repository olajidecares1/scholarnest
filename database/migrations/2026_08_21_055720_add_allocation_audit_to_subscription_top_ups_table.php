<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records what the Super Admin actually decided, alongside what the school
 * asked for.
 *
 * Until now a top-up stored one number, `additional_students_count`, and
 * approving it simply added that number to the subscription. That made the
 * school's request the decision. The Basic-plan rule is the opposite: the
 * Super Admin verifies the payment and determines the allocation, which may
 * differ from what was requested if the amount actually paid does not match.
 *
 * So `additional_students_count` keeps its meaning, what the school
 * REQUESTED, and these columns record the decision and its effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_top_ups', function (Blueprint $table) {
            // The per-student price at the moment of submission. Snapshotted
            // rather than read back off the plan later, because the Super Admin
            // may change the plan price afterwards and that must not silently
            // rewrite the arithmetic of a payment already made.
            $table->decimal('price_per_student', 12, 2)->nullable()->after('additional_amount');

            // What the Super Admin actually allocated. Null until approved, and
            // it is this, never additional_students_count, that gets added to
            // the subscription.
            $table->unsignedInteger('approved_students_count')->nullable()->after('price_per_student');

            // The allocation before and after approval. Both could be derived
            // by replaying history, but storing them means a dispute is settled
            // by reading one row rather than reconstructing a sequence.
            $table->unsignedInteger('previous_students_count')->nullable()->after('approved_students_count');
            $table->unsignedInteger('new_students_count')->nullable()->after('previous_students_count');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_top_ups', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_student',
                'approved_students_count',
                'previous_students_count',
                'new_students_count',
            ]);
        });
    }
};
