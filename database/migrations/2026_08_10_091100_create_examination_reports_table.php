<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('teacher_remark')->nullable();
            $table->text('principal_remark')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('last_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['examination_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_reports');
    }
};
