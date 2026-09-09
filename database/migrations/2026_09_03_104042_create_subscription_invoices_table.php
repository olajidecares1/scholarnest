<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AkademicNest's invoices to its schools.
 *
 * NOT the `invoices` table, which is a school billing its own parents for
 * school fees. Two different documents that happen to share a word: one is a
 * school's business with a family, this one is AkademicNest's business with a
 * school. Keeping them apart means a change to fee billing can never quietly
 * alter what a school was charged for its subscription.
 *
 * EVERY MONEY FIGURE HERE IS A SNAPSHOT, not a reference. A plan's price is
 * editable by a Super Admin, and an invoice that recomputed itself from the
 * plan would silently restate what a school was charged months after they
 * paid it. What the school was billed is a fact about that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Human-facing, unique, and never reused: SN-2026-000001.
            $table->string('number')->unique();

            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Exactly one of these two is set. A subscription invoice bills
            // for a plan; a top-up invoice bills for extra student licences.
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_top_up_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('issued_at');

            // Snapshots of who was billed, so an invoice re-read years later
            // shows the school as it was, not as it is now.
            $table->string('billed_to_name');
            $table->string('billed_to_email')->nullable();
            $table->string('billed_to_phone')->nullable();

            // Snapshots of what was billed.
            $table->string('plan_name');
            $table->string('billing_cycle')->nullable();
            $table->string('description');
            $table->unsignedInteger('licences')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();

            $table->string('currency', 3)->default('NGN');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // The Super Admin's invoice history for one school, newest first.
            $table->index(['school_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
