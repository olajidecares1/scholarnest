<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_top_ups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('additional_students_count');
            $table->decimal('additional_amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_method');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('status')->default('pending_verification');
            $table->string('reference')->unique();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_top_ups');
    }
};
