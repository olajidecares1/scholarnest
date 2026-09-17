<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A test made from one year of a multi-year past-question document.
 *
 * A teacher who uploads "JAMB Literature 2010–2018" into one test gets one test
 * per year, the way the papers were actually sat. These two columns are how a
 * later "Read this document again" finds the year tests it made the first time
 * and refills them, instead of making a second set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_tests', function (Blueprint $table) {
            $table->foreignId('source_upload_id')->nullable()->after('staff_id')
                ->constrained('cbt_test_document_uploads')->nullOnDelete();
            $table->unsignedSmallInteger('source_year')->nullable()->after('source_upload_id');

            $table->index(['source_upload_id', 'source_year']);
        });
    }

    public function down(): void
    {
        Schema::table('cbt_tests', function (Blueprint $table) {
            $table->dropIndex(['source_upload_id', 'source_year']);
            $table->dropConstrainedForeignId('source_upload_id');
            $table->dropColumn('source_year');
        });
    }
};
