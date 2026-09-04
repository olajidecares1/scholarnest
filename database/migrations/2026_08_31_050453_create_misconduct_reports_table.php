<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Something a member of the public saw a pupil do outside school.
 *
 * A neighbour, a shopkeeper, a bus driver. They are not a parent and have no
 * account, so the form asks for as little as it can: a name, what happened,
 * and whatever they photographed. No email, no telephone, no pupil to name -
 * requiring a reporter to identify the child would stop most reports being
 * made at all, and identifying the child is the school's job, not theirs.
 *
 * Attachments go in their OWN table rather than a column of paths. A report
 * with three photographs is normal, and a JSON column of file paths is a
 * thing you cannot join, count, or delete one of.
 *
 * Everything is scoped to a school and reviewed by that school's own
 * administrators. Nothing here is ever public: see the private disk the files
 * are written to, and MisconductReportController for the only route that
 * serves them back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('misconduct_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('reporter_name');
            $table->text('description');

            // Where the reporter says it happened, if they say. Optional
            // because a report without it is still worth having.
            $table->string('location')->nullable();

            $table->string('status')->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::create('misconduct_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('misconduct_report_id')->constrained()->cascadeOnDelete();

            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('misconduct_report_attachments');
        Schema::dropIfExists('misconduct_reports');
    }
};
