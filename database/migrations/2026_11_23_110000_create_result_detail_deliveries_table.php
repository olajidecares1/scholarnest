<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One message carrying a student's result details (result link, token and
 * admission number) to one parent/guardian, on one channel.
 *
 * The sending history, and what makes "retry the failed ones" possible
 * without resending what already went. The token itself is NOT stored here:
 * it is read from the token record at the moment of sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_detail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // One press of a Send button: every row it created shares this.
            $table->uuid('batch')->index();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_checking_pin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('examination_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel', 20);
            $table->string('recipient')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_detail_deliveries');
    }
};
