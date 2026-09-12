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

/**
 * Create the FIRST AkademicNest Team account, where nobody can type.
 *
 * make:super-admin prompts for everything, which is right on a machine with a
 * terminal and impossible on one without. A managed platform runs artisan
 * one-shot with no stdin: the prompt never renders, nothing is ever typed, and
 * the command hangs or dies. On a fresh deployment that leaves no way in at
 * all - there is no public registration route for this role by design, and
 * super-admin:reset-password needs an account to already exist.
 *
 * So this reads the three values from the ENVIRONMENT instead. That is the
 * distinction worth being precise about, because the sibling command's warning
 * is about a different thing:
 *
 *   A COMMAND-LINE ARGUMENT is visible in shell history, in `ps` output to
 *   every other user on the box, and in process logs. That is why neither this
 *   command nor make:super-admin will accept one.
 *
 *   AN ENVIRONMENT VARIABLE on a managed platform is the same place APP_KEY
 *   and DB_PASSWORD already live. It is write-only in the dashboard, not
 *   echoed back, and not in git.
 *
 * DELETE THE THREE VARIABLES ONCE THIS HAS RUN. They are not needed again, and
 * a password that stays in the environment is a password that stays readable
 * to anyone who can open the dashboard. The command says so when it finishes.
 *
 * IT REFUSES TO RUN WHEN A TEAM ACCOUNT ALREADY EXISTS. This is a bootstrap,
 * not an account factory: once there is somebody to sign in, further accounts
 * go through make:super-admin, where a human types the password and no copy of
 * it is left sitting in the environment.
 */
class BootstrapSuperAdminCommand extends Command
{
    protected $signature = 'super-admin:bootstrap';

    protected $description = 'Create the first AkademicNest Team account from environment variables, for platforms with no interactive shell';

    public function handle(): int
    {
        if (User::where('role', UserRole::SuperAdmin)->exists()) {
            $this->components->error(
                'A AkademicNest Team account already exists. This command only bootstraps the first one - '
                .'use `make:super-admin` to add another, or `super-admin:reset-password` to get back into this one.'
            );

            return self::FAILURE;
        }

        $name = (string) env('SUPER_ADMIN_NAME', '');
        $email = (string) env('SUPER_ADMIN_EMAIL', '');
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($name === '' || $email === '' || $password === '') {
            $this->components->error('Set SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in the environment, then run this again.');

            return self::FAILURE;
        }

        // The SAME rules every other password on the platform is held to.
        //
        // make:super-admin checks only min:12, which means the one account
        // nobody can reset from above is the one account held to the weakest
        // standard. NotDerivedFromIdentity matters most here of all: the first
        // account's password is the one most likely to be the owner's own name
        // and a year, because it is chosen in a hurry during a deployment.
        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'string', Password::defaults(), new NotDerivedFromIdentity([$name, $email])],
            ],
        );

        if ($validator->fails()) {
            // Field names and reasons only. The value itself is never echoed:
            // this output goes to a deployment log that outlives the run.
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'username' => User::generateUniqueUsernameFromEmail($email),
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'school_id' => null,
        ]);

        // Same reason as in make:super-admin: email_verified_at is not
        // fillable, so it has to be forced or the account comes out unverified.
        $user->forceFill(['email_verified_at' => now()])->save();

        AuditLog::record(
            'super-admin.bootstrapped',
            "First AkademicNest Team account {$user->name} created from the environment.",
            $user,
            actorName: 'Console',
        );

        $this->components->info("AkademicNest Team \"{$user->name}\" ({$user->email}) created successfully.");
        $this->components->warn('Now DELETE SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD from the environment.');

        return self::SUCCESS;
    }
}
