<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\NotDerivedFromIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;

/**
 * Set a new password for a AkademicNest Team account, from the server.
 *
 * The last door. Every other account on the platform has somebody who can let
 * it back in, a School Admin reissues a teacher's or a parent's password, and
 * a School Admin who forgets theirs uses the forgot-password flow. A Super
 * Admin has neither: nobody outranks them, and if the email on the account is
 * unreachable, or the mailer is not configured, the forgot-password link goes
 * nowhere. make:super-admin does not help either, it refuses an email that
 * already exists, because it creates accounts rather than repairing them.
 *
 * So this exists, and it is deliberately only reachable by somebody who
 * already has shell access to the server. That is the same level of access
 * needed to read the database or the application key, so it grants nothing
 * that was not already held.
 *
 * THE PASSWORD IS PROMPTED FOR, never passed as an argument. A password in a
 * command line is a password in the shell history, in `ps` output for every
 * other user on the box, and in any process log that happens to be running.
 */
class ResetSuperAdminPasswordCommand extends Command
{
    protected $signature = 'super-admin:reset-password {email? : The account to reset}';

    protected $description = 'Set a new password for a AkademicNest Team account that has been locked out';

    public function handle(): int
    {
        $user = $this->resolveAccount();

        if (! $user) {
            return self::FAILURE;
        }

        $this->components->info("Resetting the password for {$user->name} ({$user->email}).");

        // The same rules the web forms apply. A password set here must not be
        // weaker than one a School Admin is held to, and it must not be built
        // out of the account either, see App\Rules\NotDerivedFromIdentity.
        $identity = new NotDerivedFromIdentity([$user->name, $user->email, $user->username]);

        $new = password(
            label: 'New password',
            required: true,
            validate: fn (string $value) => Validator::make(
                ['password' => $value],
                ['password' => ['required', 'string', Password::defaults(), $identity]],
            )->errors()->first('password'),
        );

        password(
            label: 'Confirm password',
            required: true,
            validate: fn (string $value) => $value !== $new ? 'The passwords do not match.' : null,
        );

        // forceFill because `password` is not fillable on User, and this is
        // the one place a password is written without a signed-in actor.
        $user->forceFill(['password' => Hash::make($new)])->save();

        // Recorded like every other password change, and attributed to the
        // server rather than to a user, nobody was signed in to do this.
        AuditLog::record(
            'password.reset',
            "Password reset from the console for AkademicNest Team {$user->name}.",
            $user,
            actorName: 'Console',
        );

        $this->components->info('Password updated. Sign in at the AkademicNest Team dialog.');

        if (! $user->is_active) {
            $this->components->warn(
                'This account is deactivated, so the new password will not admit it. '
                .'Reactivate it before signing in.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * The account to reset, named on the command line, or chosen from a list.
     *
     * Only Super Admin accounts are offered or accepted. This command must not
     * become a way to take over a school's account from the console.
     */
    private function resolveAccount(): ?User
    {
        $accounts = User::where('role', UserRole::SuperAdmin)->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->components->error('There are no AkademicNest Team accounts. Run `php artisan make:super-admin` instead.');

            return null;
        }

        if ($email = $this->argument('email')) {
            $user = $accounts->firstWhere('email', $email);

            if (! $user) {
                $this->components->error("No AkademicNest Team account with the email [{$email}].");

                return null;
            }

            return $user;
        }

        $chosen = select(
            label: 'Which account?',
            options: $accounts->mapWithKeys(fn (User $user) => [
                $user->id => $user->email.' ('.$user->name.')'.($user->is_active ? '' : ' (deactivated)'),
            ])->all(),
        );

        return $accounts->firstWhere('id', (int) $chosen);
    }
}
