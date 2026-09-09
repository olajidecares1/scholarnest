<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear out the audit entries the doubled listener already wrote.
 *
 * LogSuccessfulLogin and LogFailedLogin were registered twice - once by
 * Laravel's discovery of app/Listeners, once by hand in AppServiceProvider -
 * so every sign-in wrote its entry twice. Fixing the registration stops new
 * ones; it does nothing about the pairs already sitting in the log, and the
 * AkademicNest Team would go on seeing the same school name twice for every
 * historical sign-in.
 *
 * DELIBERATELY NARROW. Only 'login' and 'login.failed' are touched, because
 * those are the only two actions those listeners write. Rows are matched on
 * every field that identifies the event - action, description, subject, actor
 * and the second it happened - and the earliest id of each group is kept. Two
 * people signing in during the same second produce different descriptions, so
 * they are different groups and both survive.
 *
 * Not reversible: there is nothing worth restoring, and inventing a second
 * copy of an audit entry is the opposite of what an audit log is for.
 */
return new class extends Migration
{
    public function up(): void
    {
        $identity = ['action', 'description', 'subject_type', 'subject_id', 'user_id', 'user_name', 'created_at'];

        $survivors = DB::table('audit_logs')
            ->whereIn('action', ['login', 'login.failed'])
            ->selectRaw('MIN(id) as id')
            ->groupBy($identity)
            ->pluck('id');

        $removed = DB::table('audit_logs')
            ->whereIn('action', ['login', 'login.failed'])
            ->whereNotIn('id', $survivors)
            ->delete();

        if ($removed > 0) {
            info("Removed {$removed} duplicate login audit entries left by the doubled listener.");
        }
    }

    public function down(): void
    {
        // Nothing to put back. See the note above.
    }
};
