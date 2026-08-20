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
        Schema::create('cbt_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_test_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('score', 5, 2)->nullable();
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->timestamps();

            $table->unique(['student_id', 'cbt_test_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_attempts');
    }
};
