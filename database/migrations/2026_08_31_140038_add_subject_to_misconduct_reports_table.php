<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a conduct report is ABOUT, in a few words.
 *
 * The inbox lists reports by topic now rather than printing the whole account
 * of what happened into the listing, and a report had no topic to list, only
 * a reporter, a description and a status. An enquiry already had "subject";
 * this is the same idea for the report beside it.
 *
 * Nullable, and it stays nullable. It is optional on the public form, because
 * the person filling it in is a neighbour or a shopkeeper who should not be
 * made to summarise before they can tell the school what they saw. Reports
 * without one fall back to a summary drawn from the description, see
 * MisconductReport::topic().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('misconduct_reports', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('reporter_name');
        });
    }

    public function down(): void
    {
        Schema::table('misconduct_reports', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};
