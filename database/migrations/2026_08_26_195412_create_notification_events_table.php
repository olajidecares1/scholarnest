<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per thing the EduNest Team has already been told about.
 *
 * Removing the duplicate listener that caused the reported double-up fixes
 * that particular fault. It does not stop the next one: a double-clicked form,
 * a refreshed POST, a retried request, a queued job delivered twice, or a
 * listener registered twice again in a year's time all end the same way - the
 * same event announced more than once.
 *
 * So the guarantee lives here rather than in the code that happens to send.
 * Before anything is sent, its event key is INSERTED. The unique index is what
 * makes that a claim: a second attempt for the same event fails the insert and
 * sends nothing. Checking "has this been sent?" and then sending would leave a
 * gap between the two questions wide enough for two simultaneous requests to
 * both pass - a unique index has no such gap.
 *
 * Keys are readable on purpose ("school.registered:<uuid>"), because the first
 * thing anybody investigating a missing notification wants is to look one up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();

            // The event, not the notification: "this registration has been
            // announced", regardless of how many people it went to.
            $table->string('event_key')->unique();

            // What was sent for it. Only ever read by a person working out why
            // something did or did not arrive.
            $table->string('notification');

            // How many accounts it reached. Zero is worth being able to see -
            // it means the event was announced to nobody, which is a
            // configuration problem rather than a duplicate one.
            $table->unsignedInteger('recipients')->default(0);

            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
