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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('tagline');
            $table->string('currency', 3)->default('NGN');
            $table->decimal('price_per_student_per_term', 12, 2)->nullable();
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_per_term', 12, 2)->nullable();
            $table->boolean('has_custom_pricing')->default(false);
            $table->json('features');
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
