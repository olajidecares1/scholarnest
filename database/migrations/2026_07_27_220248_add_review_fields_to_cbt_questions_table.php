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
        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->foreignId('cbt_document_upload_id')->nullable()->after('cbt_exam_id')->constrained()->nullOnDelete();
            $table->boolean('needs_review')->default(false)->after('sort_order');
            $table->text('review_notes')->nullable()->after('needs_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cbt_document_upload_id');
            $table->dropColumn(['needs_review', 'review_notes']);
        });
    }
};
