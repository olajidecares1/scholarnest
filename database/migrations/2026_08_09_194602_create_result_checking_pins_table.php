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
        Schema::create('result_checking_pins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('max_uses')->default(4);
            $table->unsignedTinyInteger('uses_count')->default(0);
            $table->foreignId('bound_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_checking_pins');
    }
};
