<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the result PIN into a narrowly scoped result token.
 *
 * The old model was a WAEC scratch card: a school bought a batch of unbound
 * PINs, and whichever student's admission number was typed first is the one a
 * PIN bound itself to. That is a general-purpose access code. The rule here is
 * the opposite, one token authorises one student's one result and nothing
 * else, decided when the token is created rather than when it is first used.
 *
 * Storage changes too. The plain `code` column meant anyone who could read the
 * table could read every live token. Now:
 *
 *   token_hash       SHA-256, unique and indexed, what verification looks up.
 *                    Tokens are high-entropy random strings, so a fast hash is
 *                    the right tool: there is nothing worth brute-forcing, and
 *                    it keeps verification a single indexed lookup rather than
 *                    the full-table scan bcrypt would force.
 *
 *   token_encrypted  Laravel's encrypted cast, so a school can redisplay a
 *                    token it needs to hand to a parent again. Verification
 *                    never touches it. A stolen database alone yields nothing,
 *                    because the application key is not in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->char('token_hash', 64)->nullable()->after('uuid');
            $table->text('token_encrypted')->nullable()->after('token_hash');

            // Set when the token is issued and bound. A row without it predates
            // this change and is treated as unusable stock.
            $table->timestamp('issued_at')->nullable()->after('uses_count');

            // Optional lifetime. Null means it does not expire on its own.
            $table->timestamp('expires_at')->nullable()->after('issued_at');

            $table->timestamp('last_accessed_at')->nullable()->after('expires_at');

            // Which term a token is for is already implied by the examination,
            // an examination carries school, class, term and session, so no
            // extra column is needed. This index makes "does this student
            // already have a live token for this result?" cheap.
            $table->index(['school_id', 'bound_student_id', 'examination_id'], 'result_tokens_student_exam_index');
        });

        // Carry existing codes across so nothing is lost. Every token on this
        // installation is unbound, unassigned and unused, so this is really
        // housekeeping, but another installation may have live ones.
        DB::table('result_checking_pins')->orderBy('id')->chunkById(200, function ($pins) {
            foreach ($pins as $pin) {
                if (blank($pin->code)) {
                    continue;
                }

                DB::table('result_checking_pins')->where('id', $pin->id)->update([
                    'token_hash' => hash('sha256', $pin->code),
                    'token_encrypted' => Crypt::encryptString($pin->code),
                ]);
            }
        });

        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->unique('token_hash');
        });

        // Only once every code has been copied across.
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->dropUnique('result_checking_pins_code_unique');
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->string('code')->nullable();
        });

        DB::table('result_checking_pins')->orderBy('id')->chunkById(200, function ($pins) {
            foreach ($pins as $pin) {
                if (blank($pin->token_encrypted)) {
                    continue;
                }

                DB::table('result_checking_pins')->where('id', $pin->id)->update([
                    'code' => Crypt::decryptString($pin->token_encrypted),
                ]);
            }
        });

        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->dropIndex('result_tokens_student_exam_index');
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'token_encrypted', 'issued_at', 'expires_at', 'last_accessed_at']);
        });
    }
};
