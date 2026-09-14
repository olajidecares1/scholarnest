<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a school actually is.
 *
 * A school's address, telephone and email lived on its WEBSITE record, and
 * the website is a Standard and Exclusive feature. So a Basic school had
 * nowhere to put an address at all, which meant the back of its ID cards had
 * no "if found, please return to" and its result sheets had no letterhead.
 * The two places a school's address matters most were the two a Basic school
 * could not reach.
 *
 * These belong to the school rather than to one of its features, so they sit
 * on the school. Existing website details are copied across, so no Standard
 * school has to type its address a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('contact_address', 500)->nullable()->after('current_session');
            $table->string('contact_phone', 60)->nullable()->after('contact_address');
            $table->string('contact_email')->nullable()->after('contact_phone');
        });

        if (! Schema::hasTable('school_websites')) {
            return;
        }

        // Copy what schools have already written on their website, so nothing
        // is lost and nobody re-types an address they have already given us.
        foreach (['contact_address', 'contact_phone', 'contact_email'] as $column) {
            if (! Schema::hasColumn('school_websites', $column)) {
                continue;
            }

            DB::table('schools')->update([
                $column => DB::raw(
                    "(select {$column} from school_websites where school_websites.school_id = schools.id limit 1)"
                ),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['contact_address', 'contact_phone', 'contact_email']);
        });
    }
};
