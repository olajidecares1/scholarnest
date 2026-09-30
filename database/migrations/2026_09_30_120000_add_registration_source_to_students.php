<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each student/pupil was registered: one at a time from the Add Student
 * form, or as part of a bulk upload. Shown to the School Admin when a new
 * registration is refused as a duplicate, so they can tell where the existing
 * record came from.
 *
 * Nullable, and left empty on the students already registered: the system did
 * not record this before, and guessing would put a wrong answer on screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('registration_source', 16)->nullable()->after('admission_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('registration_source');
        });
    }
};
