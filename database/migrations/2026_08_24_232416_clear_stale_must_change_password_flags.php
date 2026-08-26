<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Release accounts still flagged "must change password".
 *
 * That flag blocked every page except the settings screen until the user set
 * their own password there. Self-service password changes are gone - the
 * School Admin is the sole authority on credentials now - so a flagged account
 * would be redirected to a page with no form on it, and redirected again on
 * the next click. A locked account with no exit.
 *
 * The middleware that enforced it has been removed, which is the real fix.
 * This clears the flags so the column does not sit there asserting something
 * about accounts that is no longer true of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['students', 'staff', 'guardians'] as $table) {
            DB::table($table)->where('must_change_password', true)->update([
                'must_change_password' => false,
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible, and should not be: re-flagging these accounts would
        // put the lockout back without the page that used to release it.
    }
};
