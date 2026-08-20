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
        Schema::table('examination_scores', function (Blueprint $table) {
            $table->decimal('test_score', 5, 2)->nullable()->after('score');
            $table->decimal('exam_score', 5, 2)->nullable()->after('test_score');
            $table->string('grade_override')->nullable()->after('remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examination_scores', function (Blueprint $table) {
            $table->dropColumn(['test_score', 'exam_score', 'grade_override']);
        });
    }
};
