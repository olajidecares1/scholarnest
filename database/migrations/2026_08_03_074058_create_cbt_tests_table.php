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
        Schema::create('cbt_tests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('title');
            $table->string('subject');
            $table->string('class_name');
            $table->string('session')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedTinyInteger('pass_mark')->default(50);
            $table->string('status')->default('draft');
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'class_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_tests');
    }
};
