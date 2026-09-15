<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two things the email system was missing.
 *
 * EMAIL DELIVERIES. Every transactional email (welcome, invoice, top-up
 * confirmation, password reset code) is recorded with whether it was actually
 * handed to the mail server, and why not when it was not. Before this, a
 * failed send either crashed the page after the approval had already been
 * saved, or, with the mailer left on "log", disappeared without a trace while
 * the page said everything was fine.
 *
 * RESET CODES. The forgot-password flow is now a 6-digit code typed on the
 * page, with no link. The code lives in the existing password_reset_tokens
 * row (hashed), and the row gains a count of wrong attempts and the moment the
 * code was verified, so a code can be tried a limited number of times and is
 * spent once it has been used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();

            // What the email is: welcome, invoice, top-up-confirmation, ...
            $table->string('kind', 40);

            // What it was about: a subscription, a top-up, a user.
            $table->nullableMorphs('about');

            $table->string('recipient', 255);
            $table->string('subject', 255)->nullable();
            $table->string('status', 10);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['about_type', 'about_id', 'kind']);
        });

        // Unfinished links from the old flow cannot be completed by the new one.
        DB::table('password_reset_tokens')->delete();

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('token');
            $table->timestamp('verified_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'verified_at']);
        });

        Schema::dropIfExists('email_deliveries');
    }
};
