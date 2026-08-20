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
        Schema::create('cbt_test_document_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cbt_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type');
            $table->string('status')->default('pending');
            $table->unsignedInteger('questions_extracted_count')->default(0);
            $table->unsignedInteger('questions_needing_review_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('ai_response')->nullable();
            $table->json('extracted_images')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_test_document_uploads');
    }
};
