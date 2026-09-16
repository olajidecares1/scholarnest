<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a question read from a past-questions document needs to carry.
 *
 * QUESTIONS gain the number the paper printed, the passage or instruction it
 * shares with its neighbours, the explanation where the paper gives one, and a
 * fingerprint of their wording, which is how the same question uploaded twice
 * is recognised and not added twice.
 *
 * CATALOGUE QUESTIONS gain a published flag. Questions read from a document
 * wait for the AkademicNest Team to review them before any student sees them;
 * everything already in the catalogue stays published, and questions typed in
 * by hand are published as they are saved.
 *
 * UPLOADS gain the file's hash, so the same document uploaded again is caught
 * before it is imported again, the warnings extraction raised, and how many
 * duplicate questions were skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cbt_questions', 'cbt_test_questions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedSmallInteger('question_number')->nullable()->after('question_text');
                $table->text('passage')->nullable()->after('question_number');
                $table->text('explanation')->nullable()->after('passage');
                $table->string('fingerprint', 64)->nullable()->after('explanation');
            });
        }

        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('review_notes');
            $table->index(['cbt_exam_id', 'fingerprint']);
            $table->index(['cbt_exam_id', 'is_published']);
        });

        Schema::table('cbt_test_questions', function (Blueprint $table) {
            $table->index(['cbt_test_id', 'fingerprint']);
        });

        foreach (['cbt_document_uploads', 'cbt_test_document_uploads'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('file_hash', 64)->nullable()->after('mime_type')->index();
                $table->json('warnings')->nullable()->after('error_message');
                $table->unsignedInteger('duplicates_skipped')->default(0)->after('questions_needing_review_count');
            });
        }

        Schema::table('cbt_document_uploads', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_document_uploads', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });

        foreach (['cbt_document_uploads', 'cbt_test_document_uploads'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['file_hash']);
                $table->dropColumn(['file_hash', 'warnings', 'duplicates_skipped']);
            });
        }

        Schema::table('cbt_test_questions', function (Blueprint $table) {
            $table->dropIndex(['cbt_test_id', 'fingerprint']);
        });

        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->dropIndex(['cbt_exam_id', 'fingerprint']);
            $table->dropIndex(['cbt_exam_id', 'is_published']);
            $table->dropColumn('is_published');
        });

        foreach (['cbt_questions', 'cbt_test_questions'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['question_number', 'passage', 'explanation', 'fingerprint']);
            });
        }
    }
};
