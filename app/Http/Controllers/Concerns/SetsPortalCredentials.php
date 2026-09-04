<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Rules\NotDerivedFromIdentity;
use App\Services\CredentialShareMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The School Admin issuing someone their login details.
 *
 * Three account types, one rule, because they only differ in which column is
 * the username: Staff ID for a teacher, Admission Number for a student, phone
 * number for a parent. Written once so the uniqueness check, the hashing and
 * the audit entry cannot end up subtly different on the guardian page from the
 * staff page.
 *
 * Two things this deliberately does NOT do:
 *
 *   It never stores or returns the password in plain text. The School Admin
 *   types it, it is hashed, and it is theirs to pass on. A system that could
 *   show it back later would be a system that had kept it.
 *
 *   It sets must_change_password to false. That flag exists to force a user to
 *   choose their own password on first sign-in, and users are no longer
 *   permitted to change their own - the School Admin is the authority. Leaving
 *   it true would lock every account out on the first login with nowhere to go.
 */
trait SetsPortalCredentials
{
    /**
     * What this account is publicly known by, and therefore must not be
     * protected with.
     *
     * The school's name, the person's own name, the id they sign in with and
     * their email address - the four things a stranger holding a school
     * newsletter already has. Read from the ACCOUNT rather than from the
     * request, so the check cannot be sidestepped by submitting a different
     * school name alongside the password.
     */
    protected function identityRuleFor(Model $account): NotDerivedFromIdentity
    {
        return new NotDerivedFromIdentity([
            $account->school?->name,
            $account->name ?? null,
            $account->first_name ?? null,
            $account->last_name ?? null,
            $account->email ?? null,
            $account->staff_number ?? null,
            $account->admission_number ?? null,
            $account->guardian_number ?? null,
        ]);
    }

    /**
     * Set the login username and password for one portal account.
     *
     * @param  string  $usernameColumn  staff_number, admission_number or guardian_number
     */
    protected function saveCredentials(
        Request $request,
        Model $account,
        string $usernameColumn,
        string $displayName,
        string $usernameLabel,
    ): string {
        // Only the password. The username is the account's generated ID and is
        // not accepted from the request at all - a field the interface refuses
        // to show but the controller would still honour is not read-only, it
        // is read-only-looking.
        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),

                // Not made out of the account it protects. See the rule -
                // "Greenfield2026!" passes every complexity check and is the
                // first guess anybody makes against Greenfield College.
                $this->identityRuleFor($account),
            ],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $account->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        AuditLog::record(
            'portal.credentials.issued',
            "Login details issued for {$displayName} ({$usernameLabel} {$account->{$usernameColumn}}).",
            $account,
        );

        $this->flashShareLink(
            $account,
            $displayName,
            $usernameLabel,
            (string) $account->{$usernameColumn},
            $validated['password'],
        );

        return "Login details saved for {$displayName}. Give them the {$usernameLabel} and password you entered.";
    }

    /**
     * Apply the Login Details card that sits at the foot of an Add/Edit form.
     *
     * Both fields are optional there, deliberately. A school records a teacher
     * before it decides to give them portal access, and a form that refused to
     * save a staff member without a password would make the two inseparable.
     *
     * A blank password therefore leaves the existing one alone rather than
     * clearing it - editing somebody's phone number must not silently revoke
     * their login. A blank username likewise leaves the identifier as the main
     * form set it.
     *
     * @param  string  $usernameColumn  staff_number, admission_number or guardian_number
     */
    protected function applyCredentialFields(
        Request $request,
        Model $account,
        string $usernameColumn,
        string $usernameLabel,
    ): void {
        // The password alone. The ID belongs to the system.
        $validated = $request->validate([
            'login_password' => [
                'nullable',
                'string',
                Password::defaults(),
                $this->identityRuleFor($account),
            ],
        ], [], [
            'login_password' => 'password',
        ]);

        if (blank($validated['login_password'] ?? null)) {
            return;
        }

        $account->forceFill([
            'password' => Hash::make($validated['login_password']),
            'must_change_password' => false,
        ])->save();

        AuditLog::record(
            'portal.credentials.issued',
            "Login details set from the record form ({$usernameLabel} {$account->{$usernameColumn}}).",
            $account,
        );

        $this->flashShareLink(
            $account,
            $this->accountDisplayName($account),
            $usernameLabel,
            (string) $account->{$usernameColumn},
            $validated['login_password'],
        );
    }

    /**
     * Put the WhatsApp share link in the session, for one page view.
     *
     * This is the only moment the password exists in readable form anywhere in
     * the system - it was typed, hashed, and is about to be forgotten - so the
     * offer to share it has to be made now or not at all. Flashed rather than
     * stored for exactly that reason: a share link that survived the page
     * would be a password sitting in the session.
     */
    private function flashShareLink(
        Model $account,
        string $personName,
        string $idLabel,
        string $identifier,
        string $password,
    ): void {
        $school = $account->school;

        session()->flash('credential_share', app(CredentialShareMessage::class)->build(
            $school,
            $personName,
            $idLabel,
            $identifier,
            $password,
            $this->loginUrlFor($account, $school),
            $account->phone ?? null,
        ) + ['person' => $personName, 'identifier' => $identifier, 'id_label' => $idLabel]);
    }

    /**
     * The sign-in page this person actually uses - each portal has its own,
     * behind the school's own token, so a generic address would be no help.
     */
    private function loginUrlFor(Model $account, School $school): string
    {
        return match (true) {
            $account instanceof Staff => $school->portalLoginUrl('staff'),
            $account instanceof Student => $school->portalLoginUrl('student'),
            $account instanceof Guardian => $school->portalLoginUrl('guardian'),
            default => url('/'),
        };
    }

    private function accountDisplayName(Model $account): string
    {
        return match (true) {
            $account instanceof Guardian => $account->name,
            default => method_exists($account, 'fullName') ? $account->fullName() : (string) $account->getKey(),
        };
    }

    /**
     * The wa.me link for one person's login details, for the Login Details
     * card on their own page.
     *
     * No password: one exists in readable form only for the page view
     * immediately after it is set, and that moment is the banner's. This
     * carries the school, the person, their ID and the sign-in link, which is
     * what a School Admin needs to hand over at any other time.
     *
     * @return array{message: string, url: string, phone: ?string}
     */
    protected function credentialShareLink(Model $account, string $idLabel, string $identifier): array
    {
        $school = $account->school;

        return app(CredentialShareMessage::class)->build(
            $school,
            $this->accountDisplayName($account),
            $idLabel,
            $identifier,
            null,
            $this->loginUrlFor($account, $school),
            $account->phone ?? null,
        );
    }
}
