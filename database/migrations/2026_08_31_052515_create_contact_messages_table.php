<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An enquiry sent from the school's own website.
 *
 * The contact form used to be a GET that went nowhere - it looked like a form
 * and did nothing, which is worse than not having one, because a parent who
 * fills it in believes they have been in touch.
 *
 * Kept separate from support tickets: a ticket is the school asking ScholarNest
 * for help, this is a stranger asking the school a question. Same shape,
 * entirely different audience, and merging them would put a parent's enquiry
 * in front of our support staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');

            $table->timestamp('read_at')->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
