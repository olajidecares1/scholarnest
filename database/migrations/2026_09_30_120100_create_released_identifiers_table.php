<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admission numbers and Staff IDs freed by a deletion, waiting to be issued
 * again. IdentifierGenerator gives the lowest one to the next student or staff
 * member registered, before the school's counter moves on.
 *
 * Kept as a list rather than worked out from the gaps in the numbering,
 * because a number can be reserved a moment before its record is saved, and
 * a gap-hunt would hand that same number to a second request. Only a number
 * that was actually deleted is reissued.
 *
 * The numbers already deleted before this table existed are recorded here
 * too, so they are reused from now on rather than left as permanent gaps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('released_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(['school_id', 'type', 'sequence']);
        });

        $now = now();

        DB::table('schools')
            ->whereNotNull('school_code')
            ->orderBy('id')
            ->select(['id', 'school_code', 'next_admission_sequence', 'next_staff_sequence'])
            ->chunk(100, function ($schools) use ($now) {
                foreach ($schools as $school) {
                    $code = mb_strtolower((string) $school->school_code);

                    $admissions = [];
                    foreach (DB::table('students')->where('school_id', $school->id)->pluck('admission_number') as $number) {
                        if (preg_match('/^'.preg_quote($code, '/').'-.+-[^-]+-(\d+)$/u', mb_strtolower((string) $number), $m)) {
                            $admissions[(int) $m[1]] = true;
                        }
                    }

                    $staff = [];
                    $prefix = $school->school_code.'-STAFF-';
                    foreach (DB::table('staff')->where('school_id', $school->id)->where('staff_number', 'like', $prefix.'%')->pluck('staff_number') as $number) {
                        $tail = substr((string) $number, strlen($prefix));
                        if (ctype_digit($tail)) {
                            $staff[(int) $tail] = true;
                        }
                    }

                    $rows = [];
                    for ($sequence = 1; $sequence < (int) $school->next_admission_sequence; $sequence++) {
                        if (! isset($admissions[$sequence])) {
                            $rows[] = ['school_id' => $school->id, 'type' => 'admission', 'sequence' => $sequence, 'created_at' => $now, 'updated_at' => $now];
                        }
                    }
                    for ($sequence = 1; $sequence < (int) $school->next_staff_sequence; $sequence++) {
                        if (! isset($staff[$sequence])) {
                            $rows[] = ['school_id' => $school->id, 'type' => 'staff', 'sequence' => $sequence, 'created_at' => $now, 'updated_at' => $now];
                        }
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('released_identifiers')->insertOrIgnore($chunk);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('released_identifiers');
    }
};
