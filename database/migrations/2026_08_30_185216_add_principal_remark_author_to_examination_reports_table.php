<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who signed off a pupil's Principal remark, and when.
 *
 * The remark itself already sits on the report, and the session, term and
 * class come from the examination the report belongs to, kept there rather
 * than copied here, so there is one answer to "which term is this?" instead of
 * two that can disagree.
 *
 * What was missing is the attribution: a school with two administrators had no
 * record of which of them wrote the sentence on a child's card, or when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examination_reports', function (Blueprint $table) {
            $table->foreignId('principal_remark_by')->nullable()->after('principal_remark')->constrained('users')->nullOnDelete();
            $table->timestamp('principal_remark_at')->nullable()->after('principal_remark_by');
        });
    }

    public function down(): void
    {
        Schema::table('examination_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('principal_remark_by');
            $table->dropColumn('principal_remark_at');
        });
    }
};
