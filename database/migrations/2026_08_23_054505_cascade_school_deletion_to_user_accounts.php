<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a school takes its accounts with it.
 *
 * users.school_id was ON DELETE SET NULL, so removing a school did not remove
 * the accounts that belonged to it, it cut them loose. The row survived,
 * holding the school's address in users.email, which is UNIQUE. Registering
 * the same school again was then refused: "the school email address has
 * already been taken", pointing at an account whose school no longer existed
 * and which could not be signed in to (the school-admin guard signs an
 * account with no school straight back out).
 *
 * One controller cleaned up after the constraint by deleting the users first.
 * That is the wrong place for the rule: it only holds on the one path that
 * remembers it, and every account orphaned before that controller learned to
 * do it is still sitting in the table holding an address hostage.
 *
 * CASCADE puts the rule where it cannot be forgotten. It is safe for Super
 * Admins, their school_id is NULL, and a cascade never fires on a NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);

            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->cascadeOnDelete();
        });

        // The accounts the old behaviour left behind. A school admin with no
        // school is not a record with a use: it cannot sign in, it belongs to
        // nothing, and the only thing it still does is occupy an email address
        // and a username so that school can never register again.
        //
        // Deliberately narrow. Super Admins have a NULL school_id by design
        // and are never touched.
        $orphans = DB::table('users')
            ->where('role', 'school_admin')
            ->whereNull('school_id')
            ->get(['id', 'email']);

        if ($orphans->isEmpty()) {
            return;
        }

        // Their sign-in residue goes too. Neither table has a foreign key, so
        // no cascade reaches them: a session row keeps a deleted account's
        // login alive, and a reset token is a live way back into an account
        // that no longer exists.
        DB::table('sessions')->whereIn('user_id', $orphans->pluck('id'))->delete();
        DB::table('password_reset_tokens')->whereIn('email', $orphans->pluck('email'))->delete();

        DB::table('users')->whereIn('id', $orphans->pluck('id'))->delete();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);

            $table->foreign('school_id')
                ->references('id')
                ->on('schools')
                ->nullOnDelete();
        });

        // The orphaned accounts are not restored. They were unusable records
        // holding email addresses their schools no longer existed to justify,
        // and re-creating them would put the original fault back.
    }
};
