<?php

use App\Support\SchoolAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One registered school per physical address.
 *
 * address_fingerprint is a hash of the school's address in normal form (see
 * App\Support\SchoolAddress), and the UNIQUE index on it is what makes a
 * second registration at the same address impossible, including two that
 * arrive at the same moment, which no check in the application alone can
 * prevent. NULL for a school with no address yet, which blocks nobody.
 *
 * EXISTING SCHOOLS ARE NEVER MERGED OR DELETED. Each school's fingerprint is
 * filled from the address it already has, oldest school first. Where a later
 * school shares an address with an earlier one, its fingerprint is left empty
 * and it is logged, so the unique index can be created and both schools carry
 * on exactly as before. `php artisan schools:duplicate-addresses` lists them
 * for the AkademicNest Team to look at; it changes nothing either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('address_fingerprint', 64)->nullable()->after('contact_address');
        });

        $seen = [];

        DB::table('schools')
            ->whereNotNull('contact_address')
            ->orderBy('id')
            ->select(['id', 'contact_address'])
            ->chunkById(500, function ($schools) use (&$seen) {
                foreach ($schools as $school) {
                    $fingerprint = SchoolAddress::fingerprint($school->contact_address);

                    if ($fingerprint === null) {
                        continue;
                    }

                    if (isset($seen[$fingerprint])) {
                        Log::warning('Schools share a registered address; the later one was left without an address fingerprint. Review with: php artisan schools:duplicate-addresses', [
                            'school_id' => $school->id,
                            'first_registered_school_id' => $seen[$fingerprint],
                        ]);

                        continue;
                    }

                    $seen[$fingerprint] = $school->id;

                    DB::table('schools')->where('id', $school->id)->update(['address_fingerprint' => $fingerprint]);
                }
            });

        Schema::table('schools', function (Blueprint $table) {
            $table->unique('address_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['address_fingerprint']);
            $table->dropColumn('address_fingerprint');
        });
    }
};
