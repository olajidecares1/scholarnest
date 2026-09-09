<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each school's own result-checking address: akademicnest.com/greenfield-college/result
 *
 * The address is a column rather than simply the school's slug, because a
 * school has to be able to retire one. A result link gets printed on slips,
 * pasted into WhatsApp groups and forwarded around; when that spreads further
 * than a school wanted, it needs a way to issue a fresh address without
 * renaming itself and breaking its own landing page and portal URLs.
 *
 * It is seeded FROM the slug, so the ordinary case reads exactly like the
 * school - /greenfield-college/result - and only a school that deliberately
 * regenerates ends up with something else.
 *
 * `retired_school_result_links` is the other half of the same idea. A retired
 * address must never be handed to a second school: a parent still holding the
 * old link would otherwise be quietly walked from the school they expected to
 * a different one, which is the exact cross-school confusion this whole
 * feature exists to prevent. Retired values are kept forever and excluded when
 * a new one is generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // Nullable only so the backfill below can run; every row has one
            // by the end of this migration and the model always sets it.
            $table->string('result_link_slug')->nullable()->unique()->after('school_code');

            // Revoking without regenerating: the address stays reserved to
            // this school, it simply stops answering.
            $table->boolean('result_link_enabled')->default(true)->after('result_link_slug');
        });

        // Existing schools keep their familiar address.
        DB::statement('UPDATE schools SET result_link_slug = slug WHERE result_link_slug IS NULL');

        Schema::create('retired_school_result_links', function (Blueprint $table) {
            $table->id();

            // Kept even if the school is deleted - the point is that nobody
            // else may ever claim this address, which outlives the school.
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug')->unique();
            $table->timestamp('retired_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_school_result_links');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['result_link_slug']);
            $table->dropColumn(['result_link_slug', 'result_link_enabled']);
        });
    }
};
