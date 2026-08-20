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
        Schema::create('cbt_test_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_test_question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 1);
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['cbt_test_question_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_question_options');
    }
};
