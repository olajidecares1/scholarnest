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
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->string('orientation')->default('portrait')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('id_card_templates', function (Blueprint $table) {
            $table->string('orientation')->default('landscape')->change();
        });
    }
};
