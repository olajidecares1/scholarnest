<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Services\GoldSignature;

/**
 * The school's Principal signature, one per school, and only one.
 *
 * ON THIS PLATFORM, SCHOOL ADMIN IS THE PRINCIPAL. There is no separate
 * Principal account and no second signature to keep in step: the School
 * Admin's own registered signature IS the school's official Principal
 * signature, and everything that prints one, report cards, ID cards,
 * certificates, previews, resolves it through here.
 *
 * That is a deliberate single point. The school used to carry its own
 * `principal_signature_path` alongside the admin's registered signature, which
 * meant two answers to one question and a standing chance of a card printing
 * the stale one.
 *
 * SECURITY. The school is always a School model the caller resolved from the
 * session or from a record it already owns, never an id out of a request,
 * and the signer is found by querying THAT school's own School Admin accounts.
 * There is no principal_id or school_admin_id parameter anywhere in this
 * class, so there is nothing a teacher, pupil, guardian or another school
 * could submit to point it somewhere else. One school physically cannot
 * resolve another's: the query is scoped to the school it was handed.
 *
 * When no School Admin has registered a signature, this resolves to null and
 * documents print their ruled line unsigned, which is the honest placeholder.
 * A signature is a claim that a particular person saw the document, so
 * substituting anybody else's would be worse than leaving it blank.
 */
final class PrincipalSignature
{
    private function __construct(
        private readonly string $sourcePath,
        public readonly ?string $name,
    ) {}

    /**
     * This school's Principal signature, or null if none is registered.
     *
     * Deterministic where a school has several administrators: the
     * longest-standing account that has actually registered a signature. A
     * school with two admins gets the same signature on every document rather
     * than whichever row the database happened to return first.
     */
    public static function for(School $school): ?self
    {
        $admin = User::query()
            ->where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->whereHas('signature')
            ->with('signature')
            ->orderBy('id')
            ->first();

        if (! $admin?->signature?->path) {
            return null;
        }

        return new self(
            sourcePath: $admin->signature->path,
            name: self::nameFrom($school, $admin),
        );
    }

    /**
     * The name to print under the ruled line.
     *
     * The school's recorded Principal's Name wins, "Mr. Gregory A. Eze",
     * and the signed-in account's own name stands in where none is set,
     * because the account IS the Principal.
     *
     * With one exception. Schools are commonly registered with the
     * administrator account named after the school itself, and printing
     * "Vincent Martins College" under the words PRINCIPAL'S SIGNATURE names
     * nobody: it reads as a bug on a document a parent keeps. Better a blank
     * line, which is what an unnamed signature honestly is, and a prompt in
     * Settings to record who signs.
     */
    private static function nameFrom(School $school, ?User $admin): ?string
    {
        if (filled($school->principal_name)) {
            return $school->principal_name;
        }

        $accountName = $admin?->name;

        if (blank($accountName) || self::sameAs($accountName, $school->name)) {
            return null;
        }

        return $accountName;
    }

    private static function sameAs(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    /**
     * The gold, emboldened signature, for the screen.
     *
     * Gold is applied HERE rather than left to each template, so a document
     * added later cannot forget it, see App\Services\GoldSignature for why it
     * is baked into the image rather than applied with CSS.
     */
    public function dataUri(): ?string
    {
        return app(GoldSignature::class)->dataUriFor($this->sourcePath);
    }

    /**
     * The same image as a local filesystem path, for dompdf views only.
     */
    public function absolutePath(): ?string
    {
        return app(GoldSignature::class)->absolutePathFor($this->sourcePath);
    }

    /**
     * The name to print under the ruled line, whether or not the school has
     * recorded a formal one.
     */
    public static function nameFor(School $school): ?string
    {
        if (filled($school->principal_name)) {
            return $school->principal_name;
        }

        // Resolved even when no signature is registered, so an unsigned card
        // still carries the Principal's name where the school has recorded
        // one, or a clean blank line where it has not.
        return self::for($school)?->name ?? self::nameFrom($school, self::firstAdminOf($school));
    }

    /**
     * The school's longest-standing administrator, signature or not.
     */
    private static function firstAdminOf(School $school): ?User
    {
        return User::query()
            ->where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->orderBy('id')
            ->first();
    }
}
