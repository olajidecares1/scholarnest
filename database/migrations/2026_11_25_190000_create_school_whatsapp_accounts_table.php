<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's own registered WhatsApp Business number, through Meta's
 * WhatsApp Cloud API: result details to its parents go out from it,
 * automatically, with nobody logged in to WhatsApp anywhere.
 *
 * One per school. The access token is encrypted at rest (see the model's
 * casts) and never shown back in full.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();

            // As Meta shows it, e.g. "+234 814 127 9663", read back from Meta
            // when the details are checked.
            $table->string('display_number')->nullable();
            $table->string('verified_name')->nullable();

            $table->string('phone_number_id', 64);
            $table->text('access_token');
            $table->string('result_template', 512);
            $table->string('template_language', 16)->default('en');

            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_whatsapp_accounts');
    }
};
