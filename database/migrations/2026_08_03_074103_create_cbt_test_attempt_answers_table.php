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
        Schema::create('cbt_test_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_test_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_test_question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['cbt_test_attempt_id', 'cbt_test_question_id'], 'cbt_test_attempt_answers_attempt_question_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_attempt_answers');
    }
};
