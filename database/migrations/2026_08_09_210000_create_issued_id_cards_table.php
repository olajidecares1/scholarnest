<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_id_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('holder_type');
            $table->uuid('holder_uuid');
            $table->foreignId('id_card_template_id')->nullable()->constrained('id_card_templates')->nullOnDelete();
            $table->string('card_number')->unique();
            $table->unsignedInteger('serial_number');
            $table->string('status')->default('active');
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'holder_type', 'holder_uuid']);
            $table->index(['school_id', 'holder_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_id_cards');
    }
};
