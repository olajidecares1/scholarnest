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
        Schema::create('portal_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('guard', 20);
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->string('laravel_session_id', 100);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['guard', 'school_id']);
            $table->index('laravel_session_id');
            $table->index(['authenticatable_type', 'authenticatable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_sessions');
    }
};
