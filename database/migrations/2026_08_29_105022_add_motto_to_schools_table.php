<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two lines of words a school puts on its own documents.
 *
 * A report card carries two of them and they are not the same line: the motto
 * sits under the school's name on the letterhead ("Raising Excellence,
 * Building Leaders"), and the core values run along the foot of the page
 * ("Discipline, Knowledge, Character, Excellence").
 *
 * Both lived only on the website record, which is a Standard and Exclusive
 * feature, so a Basic school's report cards printed its name twice and no
 * values at all. Like the address before them, these belong to the school
 * rather than to one of its features, so they sit on the school and every plan
 * can set them.
 *
 * Whatever a school has already written on its website is copied across, so
 * nobody types a motto they have already given us.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('motto', 160)->nullable()->after('contact_email');
            $table->string('core_values', 200)->nullable()->after('motto');
        });

        if (! Schema::hasTable('school_websites')) {
            return;
        }

        // school column => the website column it inherits from.
        $inherits = [
            'motto' => 'slogan_tagline',
            'core_values' => 'footer_text',
        ];

        foreach ($inherits as $column => $source) {
            if (! Schema::hasColumn('school_websites', $source)) {
                continue;
            }

            DB::table('schools')->update([
                $column => DB::raw(
                    "(select {$source} from school_websites where school_websites.school_id = schools.id limit 1)"
                ),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['motto', 'core_values']);
        });
    }
};
