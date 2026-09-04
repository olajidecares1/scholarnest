<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit entries never recorded which school they belonged to.
 *
 * That was survivable while the only thing reading them was the ScholarNest Team's
 * platform-wide log, which wants every school's entries anyway. The moment a
 * School Admin's dashboard tried to show its own recent activity there was no
 * column to filter on - and no way to show a school its history without
 * showing it everyone else's.
 *
 * Nullable, because plenty of entries are genuinely platform-level: the
 * ScholarNest Team editing CBT question banks or plan pricing belongs to no school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();

            // Paired with id because every read is "this school's entries,
            // newest first".
            $table->index(['school_id', 'id']);
        });

        // Backfill what can be known. A School Admin's entries belong to their
        // school; entries by the ScholarNest Team, and by actors recorded only by
        // name, stay null rather than being guessed at.
        // Written as a correlated subquery rather than an UPDATE ... JOIN so
        // that it runs identically on MySQL and on the SQLite the suite uses.
        DB::table('audit_logs')
            ->whereNull('school_id')
            ->whereNotNull('user_id')
            ->update([
                'school_id' => DB::raw('(select school_id from users where users.id = audit_logs.user_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropIndex(['school_id', 'id']);
            $table->dropColumn('school_id');
        });
    }
};
