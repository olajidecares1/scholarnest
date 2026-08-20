<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->backfillMissingCodes();
        $this->deduplicateExistingCodes();

        Schema::table('schools', function (Blueprint $table) {
            $table->unique('school_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['school_code']);
        });
    }

    /**
     * school_code was previously optional and only set by an admin who
     * opted into auto-generated admission numbers / staff IDs - every other
     * school has a null value that would violate the new unique index.
     */
    private function backfillMissingCodes(): void
    {
        $existing = DB::table('schools')->whereNotNull('school_code')->pluck('school_code')->all();

        DB::table('schools')->whereNull('school_code')->orderBy('id')->get(['id'])->each(function ($school) use (&$existing) {
            do {
                $code = Str::upper(Str::random(6));
            } while (in_array($code, $existing, true));

            $existing[] = $code;

            DB::table('schools')->where('id', $school->id)->update(['school_code' => $code]);
        });
    }

    /**
     * With no uniqueness constraint ever enforced, two schools could
     * already share a code today - keep the earliest-created school's value
     * untouched and append a numeric suffix to every later duplicate.
     */
    private function deduplicateExistingCodes(): void
    {
        $duplicateCodes = DB::table('schools')
            ->select('school_code')
            ->whereNotNull('school_code')
            ->groupBy('school_code')
            ->havingRaw('count(*) > 1')
            ->pluck('school_code');

        foreach ($duplicateCodes as $code) {
            $schools = DB::table('schools')->where('school_code', $code)->orderBy('id')->get(['id']);

            $suffix = 1;

            foreach ($schools->skip(1) as $school) {
                DB::table('schools')->where('id', $school->id)->update([
                    'school_code' => "{$code}-".(++$suffix),
                ]);
            }
        }
    }
};
