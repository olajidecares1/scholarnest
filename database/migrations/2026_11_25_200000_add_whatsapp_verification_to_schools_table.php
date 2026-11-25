<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's WhatsApp number, proved to be on WhatsApp and to be theirs: a
 * code sent to it on WhatsApp was typed back. Kept as the digits verified, so
 * changing schools.whatsapp_number afterwards leaves it unverified again
 * without anything having to remember to reset it (School::verifiedWhatsApp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('whatsapp_verified_number', 20)->nullable()->after('whatsapp_number');
            $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_verified_number');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_verified_number', 'whatsapp_verified_at']);
        });
    }
};
