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
        Schema::create('cbt_test_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cbt_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_test_document_upload_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question_text');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('needs_review')->default(false);
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['cbt_test_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_questions');
    }
};
