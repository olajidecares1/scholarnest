<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The examination year the uploader gives a past-question document, for a
 * paper that never prints its own. ProcessCbtDocumentUpload files undated
 * questions under it, before falling back to a year in the file name and,
 * last of all, the year of upload. A compilation's own year headings still
 * decide where each of its questions goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_document_uploads', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('cbt_subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_document_uploads', function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }
};
