<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who redeemed a token, where that is knowable.
 *
 * The usage already recorded WHICH token, for WHICH student, and WHEN. What a
 * School Admin also asks is "who used it", and on the portals that has an
 * answer: the signed-in guardian, or the student themselves.
 *
 * Nullable because on the public check-result page there is nobody signed in.
 * A parent typing a token into a school's result address is anonymous by
 * design; the token is the credential. Recording nothing there is honest,
 * where inventing an identity would not be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_checking_pin_usages', function (Blueprint $table) {
            $table->nullableMorphs('redeemed_by');
        });
    }

    public function down(): void
    {
        Schema::table('result_checking_pin_usages', function (Blueprint $table) {
            $table->dropMorphs('redeemed_by');
        });
    }
};
