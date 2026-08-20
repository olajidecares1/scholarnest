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
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('excerpt')->nullable();
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_published')->default(true);
            $table->dateTime('published_at');
            $table->timestamps();

            $table->index(['school_id', 'is_published', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
