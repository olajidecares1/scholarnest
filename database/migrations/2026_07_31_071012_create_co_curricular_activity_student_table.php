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
        Schema::create('co_curricular_activity_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('co_curricular_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['co_curricular_activity_id', 'student_id'], 'cca_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('co_curricular_activity_student');
    }
};
