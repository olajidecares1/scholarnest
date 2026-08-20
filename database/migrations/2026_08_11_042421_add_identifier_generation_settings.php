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
        Schema::table('schools', function (Blueprint $table) {
            $table->string('school_code')->nullable()->after('slug');
            $table->boolean('auto_generate_admission_numbers')->default(false)->after('school_code');
            $table->unsignedInteger('next_admission_sequence')->default(1)->after('auto_generate_admission_numbers');
            $table->boolean('auto_generate_staff_ids')->default(false)->after('next_admission_sequence');
            $table->unsignedInteger('next_staff_sequence')->default(1)->after('auto_generate_staff_ids');
        });

        Schema::table('academic_levels', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'school_code',
                'auto_generate_admission_numbers',
                'next_admission_sequence',
                'auto_generate_staff_ids',
                'next_staff_sequence',
            ]);
        });

        Schema::table('academic_levels', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
