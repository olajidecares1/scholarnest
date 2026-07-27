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
        Schema::create('cbt_document_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cbt_exam_body_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cbt_subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type');
            $table->string('status')->default('pending');
            $table->json('detected_years')->nullable();
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
        Schema::dropIfExists('cbt_document_uploads');
    }
};
