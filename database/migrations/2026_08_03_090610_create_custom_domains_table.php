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
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('pending_verification');
            $table->string('verification_token');
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status')->default('pending');
            $table->timestamp('ssl_issued_at')->nullable();
            $table->boolean('redirect_default_domain')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_check_error')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
