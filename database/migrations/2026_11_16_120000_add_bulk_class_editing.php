<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editing a whole class at once, by uploading a revised list or in the
 * spreadsheet-style editor. See App\Services\ClassRoster.
 *
 *  - school_classes.extra_columns: the columns a school added to a class
 *    beyond the standard student details ("Locker No.", "Bus Stop"), kept
 *    even while every cell under them is empty.
 *  - students.extra_fields: each student's values for those columns.
 *  - class_update_batches: one row per bulk update, holding exactly what each
 *    record was before the update, so the update can be rolled back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->json('extra_columns')->nullable();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->json('extra_fields')->nullable();
        });

        Schema::create('class_update_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('class_name', 100);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 150)->nullable();

            // 'file' (uploaded list) or 'editor' (edited in the system).
            $table->string('source', 16);
            $table->string('file_name')->nullable();

            // Counts shown in the history: updated, added, removed, fields.
            $table->json('summary');

            // Everything needed to undo it: each touched record's values
            // before AND after, the students created, the columns added.
            $table->json('snapshot');

            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('rollback_notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'school_class_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_update_batches');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('extra_fields');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn('extra_columns');
        });
    }
};
