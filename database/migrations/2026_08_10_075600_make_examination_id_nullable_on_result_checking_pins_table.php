<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->dropForeign(['examination_id']);
            $table->foreignId('examination_id')->nullable()->change();
            $table->foreign('examination_id')->references('id')->on('examinations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->dropForeign(['examination_id']);
            $table->foreignId('examination_id')->nullable(false)->change();
            $table->foreign('examination_id')->references('id')->on('examinations')->cascadeOnDelete();
        });
    }
};
