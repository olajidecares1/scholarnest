<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The school Job Portal: vacancies a school publishes at its own address, and
 * the applications, interviews and decisions that follow.
 *
 * Built on the existing job_postings table rather than beside it, so every
 * vacancy a school has already posted carries over: an open one becomes
 * "published", a closed one "closed".
 *
 * WHAT IS PRIVATE. Applications carry a person's contact details and CV, so
 * every one of these tables is scoped to a school, and the files are paths on
 * the private disk - never URLs. See App\Http\Controllers\SchoolAdmin\
 * JobApplicationController for how they are served.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // The address a vacancy is shared at: /jobs/{public_token}. Random
            // and unguessable, so vacancies cannot be enumerated and a draft
            // cannot be found by counting; separate from the uuid the
            // dashboard uses, so nothing an applicant sees opens an admin page.
            $table->string('public_token', 40)->nullable()->after('uuid');

            $table->string('status', 20)->default('draft')->after('employment_type');
            $table->unsignedSmallInteger('openings')->nullable()->after('location');
            $table->string('salary_range', 120)->nullable()->after('openings');
            $table->text('responsibilities')->nullable()->after('description');
            $table->text('requirements')->nullable()->after('responsibilities');
            $table->text('qualifications')->nullable()->after('requirements');
            $table->string('experience_required', 150)->nullable()->after('qualifications');
            $table->text('application_instructions')->nullable()->after('experience_required');
            $table->string('contact_name', 150)->nullable()->after('application_instructions');
            $table->string('contact_email', 255)->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->string('featured_image_path')->nullable()->after('contact_phone');
            $table->timestamp('published_at')->nullable()->after('posted_at');
            $table->timestamp('closed_at')->nullable()->after('published_at');
            $table->timestamp('archived_at')->nullable()->after('closed_at');
        });

        // Carry every existing vacancy over, each with its own share address.
        DB::table('job_postings')->orderBy('id')->each(function (object $job) {
            DB::table('job_postings')->where('id', $job->id)->update([
                'public_token' => Str::random(32),
                'status' => $job->is_active ? 'published' : 'closed',
                'published_at' => $job->posted_at,
                'closed_at' => $job->is_active ? null : now(),
            ]);
        });

        // The replacement index FIRST. On MySQL the old (school_id, is_active)
        // index is what the school_id foreign key leans on, and dropping it
        // while nothing else covers school_id fails with "needed in a foreign
        // key constraint". SQLite does not enforce that, so only MySQL catches it.
        Schema::table('job_postings', function (Blueprint $table) {
            $table->unique('public_token');
            $table->index(['school_id', 'status']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_active']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::create('job_posting_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('question', 255);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();

            $table->string('full_name', 150);
            $table->string('email', 255);
            $table->string('phone', 40);
            $table->text('address')->nullable();
            $table->text('cover_letter');
            $table->text('qualifications');
            $table->unsignedTinyInteger('years_of_experience');

            // The CV, on the PRIVATE disk. The applicant's own filename is kept
            // only to name the download.
            $table->string('cv_path');
            $table->string('cv_original_name', 255);
            $table->string('cv_mime_type', 150)->nullable();
            $table->unsignedInteger('cv_size_bytes')->default(0);

            // Answers to the school's own questions, with each QUESTION'S TEXT
            // copied in beside the answer - so editing or removing a question
            // later never changes what an applicant is recorded as answering.
            $table->json('answers')->nullable();

            $table->string('status', 30)->default('new');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['job_posting_id', 'status']);
            $table->unique(['job_posting_id', 'email']);
        });

        Schema::create('job_application_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('job_interviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_for');
            $table->string('mode', 20);
            $table->string('location', 255)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();

            // When the invitation email actually went. Null if it could not be
            // sent, so the dashboard can say so instead of implying it arrived.
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        // Every status change, in order - the recruitment trail for one
        // applicant, and the record of who decided what and when.
        Schema::create('job_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_events');
        Schema::dropIfExists('job_interviews');
        Schema::dropIfExists('job_application_documents');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_posting_questions');

        Schema::table('job_postings', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        DB::table('job_postings')->update(['is_active' => DB::raw("CASE WHEN status = 'published' THEN 1 ELSE 0 END")]);

        Schema::table('job_postings', function (Blueprint $table) {
            $table->index(['school_id', 'is_active']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropIndex(['school_id', 'status']);
            $table->dropColumn([
                'public_token', 'status', 'openings', 'salary_range', 'responsibilities', 'requirements',
                'qualifications', 'experience_required', 'application_instructions', 'contact_name',
                'contact_email', 'contact_phone', 'featured_image_path', 'published_at', 'closed_at', 'archived_at',
            ]);
        });
    }
};
