<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A comment the teacher saved on purpose, with the Save button, rather than
 * one the list picked up as they wrote. Kept ones never leave the list on
 * their own; only the teacher's ✕ removes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_remarks', function (Blueprint $table) {
            $table->boolean('kept')->default(false)->after('body_hash');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_remarks', function (Blueprint $table) {
            $table->dropColumn('kept');
        });
    }
};
