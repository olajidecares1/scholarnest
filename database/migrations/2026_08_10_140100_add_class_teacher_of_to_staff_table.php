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
        Schema::table('staff', function (Blueprint $table) {
            $table->string('class_teacher_of')->nullable()->after('department');
            $table->unique(['school_id', 'class_teacher_of']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'class_teacher_of']);
            $table->dropColumn('class_teacher_of');
        });
    }
};
