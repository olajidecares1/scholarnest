<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumulative session results: an optional, per-school setting that adds the
 * First, Second and Third Term results together into one figure for the whole
 * academic session.
 *
 * OFF by default, so no school's report cards change until that school turns
 * it on. Available on every plan, which is why it is a column on the school
 * and not a plan feature.
 *
 * cumulative_average_basis decides what the session average is divided by
 * when a term has no result yet: the terms the student actually has
 * ("terms_taken"), or always three ("all_terms"). See
 * App\Enums\CumulativeAverageBasis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('cumulative_results_enabled')->default(false)->after('automatic_grading');
            $table->string('cumulative_average_basis', 16)->default('terms_taken')->after('cumulative_results_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['cumulative_results_enabled', 'cumulative_average_basis']);
        });
    }
};
