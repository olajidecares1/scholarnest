<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AkademicNest's own WhatsApp Business number, set by the Super Admin
 * (Super Admin > WhatsApp), from which result details and verification codes
 * go to every school's parents automatically. One row. What is filled in here
 * wins over the WHATSAPP_* server settings, which remain the fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number_id', 64)->nullable();
            $table->text('access_token')->nullable();          // encrypted
            $table->string('result_template', 512)->nullable();
            $table->string('verify_template', 512)->nullable();
            $table->string('template_language', 16)->nullable();
            $table->text('app_secret')->nullable();            // encrypted
            $table->string('webhook_verify_token', 128)->nullable();
            $table->string('display_number', 40)->nullable();
            $table->string('verified_name')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_whatsapp_settings');
    }
};
