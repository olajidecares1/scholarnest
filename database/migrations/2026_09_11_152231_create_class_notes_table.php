<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A class note: one Word document, sent to one or several classes at once.
 *
 * TWO TABLES, not a class_name column, because a note goes to as many classes
 * as the teacher ticks. One row per note per class is what lets a pupil's
 * "notes for my class" be an indexed lookup rather than a scan with a LIKE
 * over a packed string, and it is the relationship the feature is built on:
 *
 *     school -> staff -> class note -> selected classes -> pupils in them
 *
 * The DOCUMENT IS STORED ONCE however many classes receive it. Copying the
 * file per class, or per pupil, would multiply a 5MB upload by the size of a
 * year group for no gain at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Who sent it. Kept when the member leaves, a note in a pupil's
            // portal should not vanish because its author was deactivated,
            // so this is nullOnDelete rather than a cascade.
            $table->foreignId('staff_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();

            // The stored file. `path` is on the PRIVATE disk and is never a
            // URL: the document is served only through a controller that has
            // checked who is asking. `original_name` is what the teacher
            // called it, used for the download filename only.
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');

            // The document's text, pulled out once at upload rather than on
            // every view, so a pupil can read and copy the note without
            // owning Word. Null for a legacy .doc, whose binary format cannot
            // be read, those stay download-only, and the page says so.
            $table->longText('body_text')->nullable();

            $table->timestamps();

            // The pupil's query is "my school, newest first".
            $table->index(['school_id', 'created_at']);
        });

        Schema::create('class_note_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_note_id')->constrained()->cascadeOnDelete();

            // The class name as the school writes it, matching students.class_name.
            // A school's classes are free text elsewhere in this application,
            // so a foreign key to school_classes would refuse the very classes
            // that have pupils in them but no configured row.
            $table->string('class_name');

            // Ticking a class twice in one submission is the same note, once.
            $table->unique(['class_note_id', 'class_name']);

            // The pupil's lookup: every note for my class.
            $table->index('class_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_note_classes');
        Schema::dropIfExists('class_notes');
    }
};
