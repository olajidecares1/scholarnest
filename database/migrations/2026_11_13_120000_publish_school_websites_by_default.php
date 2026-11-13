<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A school's website is live by default.
 *
 * Every Standard and Exclusive school gets a working website at its own
 * address from day one; unpublishing is now the deliberate act. Until now a
 * website row started unpublished, and most schools never pressed Publish, so
 * their address showed "Website coming soon" indefinitely.
 *
 * Existing unpublished rows are switched on as well: before this change there
 * was no way to tell a website nobody had published yet from one an admin had
 * taken down, and the first is by far the common case. An admin who wants the
 * site offline can unpublish it again from Website > Unpublish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->change();
        });

        DB::table('school_websites')
            ->where('is_published', false)
            ->update(['is_published' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('school_websites', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->change();
        });
    }
};
