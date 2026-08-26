<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry across the two pieces of a paper that were being read and then thrown
 * away: what the paper tells candidates to do, and what each question is worth.
 *
 * Both are printed on essentially every examination paper, and without them an
 * extracted CBT is a list of questions rather than a reproduction of the exam -
 * a student sitting it has no rubric, and every question counts the same
 * regardless of what the paper said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbt_tests', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('title');
        });

        Schema::table('cbt_exams', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('year');
        });

        // Defaults to 1 so every question already stored keeps counting exactly
        // as it does today; nothing about existing scores changes.
        Schema::table('cbt_test_questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('marks')->default(1)->after('question_text');
        });

        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->unsignedSmallInteger('marks')->default(1)->after('question_text');
        });
    }

    public function down(): void
    {
        Schema::table('cbt_tests', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });

        Schema::table('cbt_exams', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });

        Schema::table('cbt_test_questions', function (Blueprint $table) {
            $table->dropColumn('marks');
        });

        Schema::table('cbt_questions', function (Blueprint $table) {
            $table->dropColumn('marks');
        });
    }
};
