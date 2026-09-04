<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One Principal signature per school, and only one.
 *
 * School Admin is the Principal on this platform, so their registered
 * signature IS the school's Principal signature. The school was also carrying
 * its own `principal_signature_path`, which meant two answers to one question:
 * an admin could redraw their signature while the school kept printing the
 * scan somebody uploaded a term ago, and nothing in the code said which was
 * right.
 *
 * Whatever each school had uploaded is moved onto its longest-standing School
 * Admin account - the same account App\Support\PrincipalSignature resolves -
 * so no school loses a signature it had already provided. An admin who has
 * since registered their own keeps theirs: a signature they drew is a better
 * claim than a file somebody uploaded on their behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('schools', 'principal_signature_path')) {
            $this->adoptUploadedSignatures();

            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('principal_signature_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('principal_signature_path')->nullable()->after('principal_name');
        });

        // Copied back rather than moved: the admin's registered signature
        // stays where it is, because it is theirs.
        foreach (DB::table('schools')->select('id')->get() as $school) {
            $path = $this->firstAdminSignaturePath((int) $school->id);

            if ($path !== null) {
                DB::table('schools')->where('id', $school->id)->update(['principal_signature_path' => $path]);
            }
        }
    }

    private function adoptUploadedSignatures(): void
    {
        $schools = DB::table('schools')
            ->whereNotNull('principal_signature_path')
            ->select('id', 'principal_signature_path')
            ->get();

        foreach ($schools as $school) {
            $admin = DB::table('users')
                ->where('school_id', $school->id)
                ->where('role', 'school_admin')
                ->orderBy('id')
                ->first();

            // No administrator to own it. Nothing to adopt it onto, and
            // inventing an owner would be worse than the school re-registering.
            if (! $admin) {
                continue;
            }

            $alreadyRegistered = DB::table('signatures')
                ->where('owner_type', User::class)
                ->where('owner_id', $admin->id)
                ->exists();

            if ($alreadyRegistered) {
                continue;
            }

            DB::table('signatures')->insert([
                'uuid' => (string) Str::uuid(),
                'school_id' => $school->id,
                'owner_type' => User::class,
                'owner_id' => $admin->id,
                'path' => $school->principal_signature_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function firstAdminSignaturePath(int $schoolId): ?string
    {
        $admin = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('role', 'school_admin')
            ->orderBy('id')
            ->first();

        if (! $admin) {
            return null;
        }

        $signature = DB::table('signatures')
            ->where('owner_type', User::class)
            ->where('owner_id', $admin->id)
            ->first();

        return $signature?->path;
    }
};
