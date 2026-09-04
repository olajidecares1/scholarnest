<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school's official stamp.
 *
 * One per school, like the Principal's signature, and kept the same way: the
 * path points at the PRIVATE disk, never the public one. A stamp is the mark
 * that makes a document official, so a downloadable copy is precisely what
 * lets somebody else make a document look official.
 *
 * Available on every plan. A stamp is not a premium feature - it is how a
 * school's own paperwork is recognised, and a Basic school's result slip needs
 * it exactly as much as an Exclusive school's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // The PROCESSED stamp, not the photograph that was uploaded. What
            // a school uploads is a picture of a stamp on paper; what is
            // stored is the mark itself on transparency, so it can sit over a
            // report card without a white square around it.
            $table->string('stamp_path')->nullable()->after('favicon_path');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('stamp_path');
        });
    }
};
