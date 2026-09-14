<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parents get an ID of their own.
 *
 * Staff have had one since the beginning and students have their admission
 * number; a parent's login was their phone number, which is not the school's
 * to control, it changes when they change handset, two parents in one family
 * may share one, and it cannot be issued at the moment the account is created.
 *
 * So guardians now carry {school_code}-PARENT-001 like everybody else, and the
 * three login identifiers become one idea: an ID the system generates and the
 * School Admin cannot edit.
 *
 * Existing guardians are backfilled in the order they were created, so a
 * school's oldest parent account is 001. Their phone and email keep working as
 * sign-in identifiers, an account that could no longer be signed into would
 * be a worse outcome than an inconsistent one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedInteger('next_guardian_sequence')->default(1)->after('next_staff_sequence');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('guardian_number')->nullable()->after('school_id');
            $table->unique(['school_id', 'guardian_number']);
        });

        // Backfill, per school, oldest first.
        DB::table('schools')->orderBy('id')->select('id', 'school_code')->chunkById(100, function ($schools) {
            foreach ($schools as $school) {
                if (! $school->school_code) {
                    continue;
                }

                $sequence = 1;

                DB::table('guardians')
                    ->where('school_id', $school->id)
                    ->orderBy('id')
                    ->select('id')
                    ->get()
                    ->each(function ($guardian) use ($school, &$sequence) {
                        DB::table('guardians')
                            ->where('id', $guardian->id)
                            ->update([
                                'guardian_number' => sprintf('%s-PARENT-%03d', $school->school_code, $sequence),
                            ]);

                        $sequence++;
                    });

                DB::table('schools')->where('id', $school->id)->update([
                    'next_guardian_sequence' => $sequence,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'guardian_number']);
            $table->dropColumn('guardian_number');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('next_guardian_sequence');
        });
    }
};
