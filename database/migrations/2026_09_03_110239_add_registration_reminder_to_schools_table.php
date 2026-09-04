<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a school was last reminded to finish registering.
 *
 * Only the SENDING is recorded here. Whether a registration is complete is not
 * a column: it is whether the school has a subscription, which the application
 * already knows and which cannot drift out of step with itself. A boolean
 * beside it would be a second answer to the same question, and the day the two
 * disagreed, a paying school would get chased for not having paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->timestamp('registration_reminder_sent_at')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('registration_reminder_sent_at');
        });
    }
};
