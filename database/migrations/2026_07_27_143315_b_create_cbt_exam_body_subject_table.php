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
        Schema::create('cbt_exam_body_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cbt_exam_body_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cbt_subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cbt_exam_body_id', 'cbt_subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_body_subject');
    }
};
