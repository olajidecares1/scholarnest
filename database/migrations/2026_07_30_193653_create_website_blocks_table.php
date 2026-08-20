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
        Schema::create('website_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('page');
            $table->string('section');
            $table->string('type');
            $table->text('content')->nullable();
            $table->text('secondary_content')->nullable();
            $table->string('url')->nullable();
            $table->decimal('x', 6, 2);
            $table->decimal('y', 6, 2);
            $table->decimal('w', 6, 2);
            $table->decimal('h', 6, 2);
            $table->json('style');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'page']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_blocks');
    }
};
