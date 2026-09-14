<?php

use App\Models\Staff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A person's registered signature, drawn by them, owned by them.
 *
 * One table rather than a column on each kind of signer. A signature is the
 * same thing whoever writes it, and the alternative was already growing into
 * three separate homes for one idea: a column on staff, another on users, and
 * the school's principal scan. Wherever a future document needs somebody's
 * mark, a certificate, an approval, a transfer letter, it asks the same
 * relation rather than a fourth column.
 *
 * OWNERSHIP IS THE POINT. The unique index on (owner_type, owner_id) is what
 * makes "one person, one signature" a property of the database rather than a
 * rule the application has to keep remembering: a second row for the same
 * signer cannot be written, so a bug that tried to register one against
 * somebody else's account fails loudly instead of quietly cross-signing.
 *
 * school_id is denormalised from the owner so a school's signatures can be
 * scoped and removed with it, the way every other tenant-owned record here is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Written out rather than morphs(), so the composite index below
            // is the only one on the pair: it serves both the uniqueness rule
            // and every lookup.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');

            $table->string('path');
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });

        // Signatures uploaded before this table existed, moved rather than
        // abandoned, a teacher who has already signed should not have to sign
        // again because the storage changed underneath them.
        if (! Schema::hasColumn('staff', 'signature_path')) {
            return;
        }

        DB::table('staff')
            ->whereNotNull('signature_path')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                DB::table('signatures')->insert($rows->map(fn ($staff) => [
                    'uuid' => (string) Str::uuid(),
                    'school_id' => $staff->school_id,
                    'owner_type' => Staff::class,
                    'owner_id' => $staff->id,
                    'path' => $staff->signature_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('photo_path');
        });

        if (Schema::hasTable('signatures')) {
            foreach (DB::table('signatures')->where('owner_type', Staff::class)->get() as $signature) {
                DB::table('staff')->where('id', $signature->owner_id)->update(['signature_path' => $signature->path]);
            }
        }

        Schema::dropIfExists('signatures');
    }
};
